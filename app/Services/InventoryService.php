<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\InventoryPackage;
use App\Models\InventoryStockLot;
use App\Models\Order;
use App\Models\OrderIngredientReservation;
use App\Models\PreparationRecipe;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * All stock movement goes through here so every change is logged.
 * Failures throw \DomainException with a message that is safe to show.
 */
final class InventoryService
{
    private const EPSILON = 0.0005;

    /**
     * Total ingredient quantities an order needs.
     *
     * @param  Collection<int, object{item: \App\Models\MenuItem, qty: int}>  $lines
     * @return array<int,float> ingredient_id => quantity
     */
    public function requirements(Collection $lines): array
    {
        $needs = [];

        foreach ($lines as $line) {
            foreach ($line->item->ingredients as $ingredient) {
                $needs[$ingredient->id] = ($needs[$ingredient->id] ?? 0.0)
                    + $ingredient->rawQuantityForPortion(
                        (float) $ingredient->pivot->quantity * (int) $line->qty,
                    );
            }
        }

        return $needs;
    }

    /** Reserve recipe stock for a pending order without reducing physical stock. */
    public function reserveForOrder(Order $order, Collection $lines): void
    {
        $off = $lines->filter(fn ($line) => ! $line->item->isOrderable())->map(fn ($line) => $line->item->name);

        if ($off->isNotEmpty()) {
            throw new \DomainException('Not available right now: ' . $off->implode(', ') . '. Please remove it and try again.');
        }

        $needs = $this->requirements($lines);

        if ($needs === []) {
            return;
        }

        $stock = Ingredient::whereIn('id', array_keys($needs))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $reserved = OrderIngredientReservation::query()
            ->whereIn('ingredient_id', array_keys($needs))
            ->where('status', 'reserved')
            ->lockForUpdate()
            ->get(['ingredient_id', 'quantity'])
            ->groupBy('ingredient_id')
            ->map(fn ($reservations): float => (float) $reservations->sum('quantity'));
        $short = [];

        foreach ($needs as $ingredientId => $quantity) {
            $ingredient = $stock->get($ingredientId);
            $available = min(
                (float) ($ingredient?->current_stock ?? 0),
                $ingredient === null ? 0.0 : $this->usableLotQuantity($ingredient->id),
            )
                - (float) ($reserved->get($ingredientId) ?? 0);

            if ($ingredient === null || $available + self::EPSILON < $quantity) {
                foreach ($lines as $line) {
                    if ($line->item->ingredients->contains('id', $ingredientId)) {
                        $short[$line->item->name] = true;
                    }
                }
            }
        }

        if ($short !== []) {
            throw new \DomainException(
                'Sorry, we do not have enough stock for: ' . implode(', ', array_keys($short)) . '. Please change your order.'
            );
        }

        foreach ($needs as $ingredientId => $quantity) {
            $ingredient = $stock->get($ingredientId);

            OrderIngredientReservation::create([
                'order_id' => $order->id,
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => $ingredient->name,
                'unit' => $ingredient->unit,
                'quantity' => round($quantity, 3),
                'status' => 'reserved',
            ]);
        }
    }

    /** Consume a pending order's reservation when it begins preparation. */
    public function consumeReservationsForOrder(Order $order, ?User $actor): void
    {
        $ingredientIds = $order->ingredientReservations()
            ->where('status', 'reserved')
            ->pluck('ingredient_id')
            ->unique()
            ->sort()
            ->values();
        $ingredients = Ingredient::query()
            ->whereIn('id', $ingredientIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $reservations = $order->ingredientReservations()
            ->where('status', 'reserved')
            ->orderBy('ingredient_id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $ingredient = $ingredients->get($reservation->ingredient_id);

            if ($ingredient === null) {
                throw new \DomainException("Reserved ingredient {$reservation->ingredient_name} no longer exists.");
            }

            $this->apply(
                $ingredient,
                'stock_out',
                (float) $reservation->quantity,
                $actor,
                'Order ' . $order->reference_number,
                $order->id,
            );
            $reservation->update(['status' => 'consumed']);
        }
    }

    /** Release unconsumed reservations or restore consumed ingredients when an order is cancelled. */
    public function releaseOrRestoreForCancelledOrder(Order $order, ?User $actor): void
    {
        $ingredientIds = $order->ingredientReservations()
            ->whereIn('status', ['reserved', 'consumed'])
            ->pluck('ingredient_id')
            ->unique()
            ->sort()
            ->values();
        $ingredients = Ingredient::query()
            ->whereIn('id', $ingredientIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $reservations = $order->ingredientReservations()
            ->whereIn('status', ['reserved', 'consumed'])
            ->orderBy('ingredient_id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            if ($reservation->status === 'reserved') {
                $reservation->update(['status' => 'released']);
                continue;
            }

            $ingredient = $ingredients->get($reservation->ingredient_id);

            if ($ingredient === null) {
                throw new \DomainException("Consumed ingredient {$reservation->ingredient_name} no longer exists.");
            }

            $this->restoreConsumedLots(
                $ingredient,
                (float) $reservation->quantity,
                $actor,
                $order,
            );
            $reservation->update(['status' => 'restored']);
        }
    }

    /**
     * Resolve a registered package barcode, or an ingredient barcode without a fixed package size.
     *
     * @return array{ingredient: Ingredient, package: ?InventoryPackage}|null
     */
    public function resolveBarcode(string $barcode): ?array
    {
        $package = InventoryPackage::query()
            ->with('ingredient')
            ->where('barcode', $barcode)
            ->where('is_active', true)
            ->first();

        if ($package !== null) {
            return ['ingredient' => $package->ingredient, 'package' => $package];
        }

        $ingredient = Ingredient::query()->where('barcode', $barcode)->first();

        return $ingredient === null ? null : ['ingredient' => $ingredient, 'package' => null];
    }

    /** Calculate receipt quantities from current server-side stock and registered package data. */
    public function previewReceipt(string $barcode, ?float $manualQuantity): array
    {
        $resolved = $this->resolveBarcode($barcode);

        if ($resolved === null) {
            throw new \DomainException('This barcode is not registered to an active package or ingredient.');
        }

        $quantity = $resolved['package'] !== null
            ? (float) $resolved['package']->package_quantity
            : $manualQuantity;

        if ($quantity === null || $quantity <= 0) {
            throw new \DomainException('Enter a package quantity before previewing this receipt.');
        }

        $before = round((float) $resolved['ingredient']->current_stock, 3);
        $quantity = round($quantity, 3);

        return [
            'ingredient' => $resolved['ingredient'],
            'package' => $resolved['package'],
            'current_stock' => $before,
            'package_quantity' => $quantity,
            'expected_stock' => round($before + $quantity, 3),
        ];
    }

    /** Add stock only after confirmation; the receipt token prevents repeated submission. */
    public function receivePackage(
        string $barcode,
        ?float $manualQuantity,
        string $receiptToken,
        string $batchNumber,
        string $expiresAt,
        string $storageZone,
        User $actor,
    ): array {
        return DB::transaction(function () use (
            $barcode,
            $manualQuantity,
            $receiptToken,
            $batchNumber,
            $expiresAt,
            $storageZone,
            $actor,
        ): array {
            if (InventoryStockLot::query()->where('receipt_token', $receiptToken)->exists()) {
                throw new \DomainException('This receipt was already confirmed. Scan the package again for a new delivery.', 409);
            }

            $package = InventoryPackage::query()
                ->where('barcode', $barcode)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            $ingredient = $package !== null
                ? Ingredient::query()->lockForUpdate()->find($package->ingredient_id)
                : Ingredient::query()->where('barcode', $barcode)->lockForUpdate()->first();

            if ($ingredient === null) {
                throw new \DomainException('This barcode is not registered to an active package or ingredient.');
            }

            $quantity = $package !== null ? (float) $package->package_quantity : $manualQuantity;

            if ($quantity === null || $quantity <= 0) {
                throw new \DomainException('Enter a package quantity before confirming this receipt.');
            }

            $quantity = round($quantity, 3);
            $before = round((float) $ingredient->current_stock, 3);
            $after = round($before + $quantity, 3);
            $ingredient->update(['current_stock' => $after]);

            $lot = InventoryStockLot::create([
                'ingredient_id' => $ingredient->id,
                'package_id' => $package?->id,
                'batch_number' => $batchNumber,
                'expires_at' => $expiresAt,
                'received_quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost' => $ingredient->unit_cost,
                'receipt_token' => $receiptToken,
                'received_by' => $actor->id,
                'received_at' => now(),
                'storage_zone' => $storageZone,
            ]);

            InventoryLog::create([
                'ingredient_id' => $ingredient->id,
                'stock_lot_id' => $lot->id,
                'user_id' => $actor->id,
                'type' => 'stock_in',
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'notes' => mb_substr(
                    "Received barcode {$barcode}; batch {$batchNumber}; expires {$expiresAt}; stored in {$storageZone}.",
                    0,
                    500,
                ),
            ]);

            return [
                'ingredient' => $ingredient,
                'lot' => $lot,
                'current_stock' => $before,
                'package_quantity' => $quantity,
                'expected_stock' => $after,
            ];
        });
    }

    /** Receive fresh goods by name, reusing the ingredient record when it already exists. */
    public function receiveFresh(
        string $name,
        string $category,
        float $quantity,
        string $unit,
        string $batchNumber,
        string $expiresAt,
        string $storageZone,
        string $receiptToken,
        User $actor,
    ): array {
        return DB::transaction(function () use (
            $name,
            $category,
            $quantity,
            $unit,
            $batchNumber,
            $expiresAt,
            $storageZone,
            $receiptToken,
            $actor,
        ): array {
            if (InventoryStockLot::query()->where('receipt_token', $receiptToken)->exists()) {
                throw new \DomainException('This fresh-goods receipt was already submitted.', 409);
            }

            $ingredient = Ingredient::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->lockForUpdate()
                ->first();

            if ($ingredient !== null && $ingredient->unit !== $unit) {
                throw new \DomainException(
                    "{$ingredient->name} is already tracked in {$ingredient->unit}; receive it using that unit."
                );
            }

            if ($ingredient === null) {
                $ingredient = Ingredient::create([
                    'name' => $name,
                    'category' => $category,
                    'current_stock' => 0,
                    'unit' => $unit,
                    'min_threshold' => 0,
                ]);
            } elseif ($ingredient->category === null) {
                $ingredient->update(['category' => $category]);
            }

            $quantity = round($quantity, 3);
            if ($quantity < 0.001) {
                throw new \DomainException('The received quantity must be at least 0.001.');
            }

            $before = round((float) $ingredient->current_stock, 3);
            $after = round($before + $quantity, 3);
            $lot = InventoryStockLot::create([
                'ingredient_id' => $ingredient->id,
                'batch_number' => $batchNumber,
                'expires_at' => $expiresAt,
                'received_quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost' => $ingredient->unit_cost,
                'receipt_token' => $receiptToken,
                'received_by' => $actor->id,
                'received_at' => now(),
                'storage_zone' => $storageZone,
            ]);

            $ingredient->update(['current_stock' => $after]);
            InventoryLog::create([
                'ingredient_id' => $ingredient->id,
                'stock_lot_id' => $lot->id,
                'user_id' => $actor->id,
                'type' => 'stock_in',
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'notes' => mb_substr(
                    "Fresh goods received; category {$category}; batch {$batchNumber}; expires {$expiresAt}; stored in {$storageZone}.",
                    0,
                    500,
                ),
            ]);

            return [
                'ingredient' => $ingredient,
                'lot' => $lot,
                'quantity' => $quantity,
                'current_stock' => $before,
                'expected_stock' => $after,
            ];
        });
    }

    /** Manual log from inventory screens. Waste and stock_out quantities are positive inputs. */
    public function logMovement(Ingredient $ingredient, string $type, float $quantity, ?User $actor, ?string $note): void
    {
        if ($type === 'waste') {
            throw new \DomainException('Waste must include a reason and details.');
        }

        DB::transaction(function () use ($ingredient, $type, $quantity, $actor, $note) {
            $locked = Ingredient::lockForUpdate()->findOrFail($ingredient->id);
            $this->assertDoesNotConsumeReservedStock($locked, $type, $quantity);
            $this->apply($locked, $type, $quantity, $actor, $note);
        });
    }

    public function recordWaste(
        Ingredient $ingredient,
        float $quantity,
        string $reason,
        string $details,
        ?User $actor,
    ): ?float {
        return DB::transaction(function () use ($ingredient, $quantity, $reason, $details, $actor): ?float {
            if (trim($reason) === '' || trim($details) === '') {
                throw new \DomainException('Waste requires a reason and details.');
            }

            $locked = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);
            $this->assertDoesNotConsumeReservedStock($locked, 'waste', $quantity);
            $movementLots = $this->apply($locked, 'waste', $quantity, $actor, trim($details), null, $reason);
            $costedLots = collect($movementLots)->filter(fn (array $lot): bool => $lot['unit_cost'] !== null);

            if ($costedLots->count() !== count($movementLots)) {
                return null;
            }

            return round($costedLots->sum(
                fn (array $lot): float => $lot['quantity'] * (float) $lot['unit_cost'],
            ), 4);
        });
    }

    /**
     * @return array{input_quantity: float, output_quantity: float, output_ingredient: Ingredient}
     */
    public function prepare(PreparationRecipe $recipe, float $batches, ?User $actor): array
    {
        return DB::transaction(function () use ($recipe, $batches, $actor): array {
            if ($batches <= 0) {
                throw new \DomainException('Preparation batch count must be greater than zero.');
            }

            $lockedRecipe = PreparationRecipe::query()
                ->with(['outputIngredient', 'lines.ingredient'])
                ->lockForUpdate()
                ->findOrFail($recipe->id);
            $output = $lockedRecipe->outputIngredient;
            $lines = $lockedRecipe->lines;
            if ($lines->isEmpty()) {
                throw new \DomainException('This preparation recipe has no usable yield.');
            }

            $ingredients = Ingredient::query()
                ->whereIn('id', $lines->pluck('ingredient_id')->push($output->id)->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $outputQuantity = round((float) $lockedRecipe->yield_quantity * $batches, 3);

            if ($outputQuantity < 0.001) {
                throw new \DomainException('This preparation recipe has no usable yield.');
            }
            if ($outputQuantity > 999999999) {
                throw new \DomainException('Prepared quantity exceeds the supported stock limit.');
            }

            $sourceLots = collect();
            $sourceNames = [];

            foreach ($lines as $line) {
                $ingredient = $ingredients->get($line->ingredient_id);
                if ($ingredient === null || $ingredient->id === $output->id) {
                    throw new \DomainException('This preparation recipe contains an invalid ingredient.');
                }

                $quantity = round((float) $line->quantity * $batches, 3);
                if ($quantity < 0.001) {
                    throw new \DomainException('Preparation quantity must be at least 0.001.');
                }
                if ($quantity > 999999999) {
                    throw new \DomainException('Preparation quantity exceeds the supported stock limit.');
                }

                $this->assertDoesNotConsumeReservedStock($ingredient, 'prep_out', $quantity);
                $movementLots = $this->apply(
                    $ingredient,
                    'prep_out',
                    $quantity,
                    $actor,
                    'Preparation for '.$output->name,
                );
                $sourceLots = $sourceLots->merge(
                    InventoryStockLot::query()->whereIn('id', collect($movementLots)->pluck('lot_id'))->get(),
                );
                $sourceNames[] = $ingredient->name.' '.$quantity.' '.$ingredient->unit;
            }

            $output = $ingredients->get($output->id);
            $stockBefore = round((float) $output->current_stock, 3);
            $stockAfter = round($stockBefore + $outputQuantity, 3);
            if ($stockAfter > 999999999) {
                throw new \DomainException("Not enough stock capacity for {$output->name}.");
            }
            $sourceExpiryDates = $sourceLots->pluck('expires_at');
            $expiresAt = $sourceExpiryDates->contains(null)
                ? null
                : $sourceExpiryDates->filter()->min();
            $storageZones = $sourceLots->pluck('storage_zone')->unique()->values();
            $storageZone = $storageZones->count() === 1 ? $storageZones->first() : null;
            $batchNumber = 'PREP-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
            $outputLot = InventoryStockLot::create([
                'ingredient_id' => $output->id,
                'batch_number' => $batchNumber,
                'expires_at' => $expiresAt,
                'received_quantity' => $outputQuantity,
                'remaining_quantity' => $outputQuantity,
                'unit_cost' => $output->unit_cost,
                'receipt_token' => 'prep-'.Str::uuid(),
                'received_by' => $actor?->id,
                'received_at' => now(),
                'storage_zone' => $storageZone,
            ]);
            $output->update(['current_stock' => $stockAfter]);
            InventoryLog::create([
                'ingredient_id' => $output->id,
                'stock_lot_id' => $outputLot->id,
                'user_id' => $actor?->id,
                'type' => 'prep_in',
                'quantity' => $outputQuantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'notes' => mb_substr(
                    'Prepared from '.implode(', ', $sourceNames).'; batch '.$batchNumber.'.',
                    0,
                    500,
                ),
            ]);

            return [
                'input_quantity' => round($lines->sum(fn ($line) => (float) $line->quantity * $batches), 3),
                'output_quantity' => $outputQuantity,
                'output_ingredient' => $output,
            ];
        });
    }

    /** Admin override: set the exact stock figure; the difference is logged as an adjustment. */
    public function override(Ingredient $ingredient, float $newStock, ?User $actor, string $note): void
    {
        DB::transaction(function () use ($ingredient, $newStock, $actor, $note) {
            $locked = Ingredient::lockForUpdate()->findOrFail($ingredient->id);
            $delta = round($newStock - (float) $locked->current_stock, 3);

            if (abs($delta) < self::EPSILON) {
                return;
            }

            $this->assertDoesNotConsumeReservedStock($locked, 'adjustment', $delta);
            $this->apply($locked, 'adjustment', $delta, $actor, 'Override: ' . $note);
        });
    }

    private function assertDoesNotConsumeReservedStock(Ingredient $ingredient, string $type, float $quantity): void
    {
        $delta = match ($type) {
            'stock_out' => -abs($quantity),
            'prep_out' => -abs($quantity),
            'waste' => -abs($quantity),
            'adjustment' => $quantity,
            default => 0.0,
        };

        if ($delta >= 0.0) {
            return;
        }

        $remaining = $this->usableLotQuantity($ingredient->id) + $delta;
        $reserved = (float) OrderIngredientReservation::query()
            ->where('ingredient_id', $ingredient->id)
            ->where('status', 'reserved')
            ->lockForUpdate()
            ->get(['quantity'])
            ->sum('quantity');

        if ($remaining + self::EPSILON < $reserved) {
            throw new \DomainException("That movement would use stock reserved for pending orders ({$ingredient->name}).");
        }
    }

    private function restoreConsumedLots(
        Ingredient $ingredient,
        float $quantity,
        ?User $actor,
        Order $order,
    ): void {
        $remaining = round($quantity, 3);
        $stockAt = round((float) $ingredient->current_stock, 3);
        $restorations = [];
        $consumedLogs = InventoryLog::query()
            ->where('order_id', $order->id)
            ->where('ingredient_id', $ingredient->id)
            ->where('type', 'stock_out')
            ->whereNotNull('stock_lot_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($consumedLogs as $consumedLog) {
            if ($remaining < 0.001) {
                break;
            }

            $lot = InventoryStockLot::query()
                ->whereKey($consumedLog->stock_lot_id)
                ->lockForUpdate()
                ->first();
            if ($lot === null) {
                continue;
            }

            $restored = round(min($remaining, abs((float) $consumedLog->quantity)), 3);
            if ($restored < 0.001) {
                continue;
            }

            $lot->update([
                'remaining_quantity' => round((float) $lot->remaining_quantity + $restored, 3),
            ]);
            $stockAfter = round($stockAt + $restored, 3);
            $restorations[] = [
                'lot_id' => $lot->id,
                'quantity' => $restored,
                'stock_before' => $stockAt,
                'stock_after' => $stockAfter,
            ];
            $stockAt = $stockAfter;
            $remaining = round($remaining - $restored, 3);
        }

        if ($restorations !== []) {
            $ingredient->update(['current_stock' => $stockAt]);
            foreach ($restorations as $restoration) {
                InventoryLog::create([
                    'ingredient_id' => $ingredient->id,
                    'stock_lot_id' => $restoration['lot_id'],
                    'user_id' => $actor?->id,
                    'order_id' => $order->id,
                    'type' => 'adjustment',
                    'quantity' => $restoration['quantity'],
                    'stock_before' => $restoration['stock_before'],
                    'stock_after' => $restoration['stock_after'],
                    'notes' => 'Cancelled order ' . $order->reference_number,
                ]);
            }
        }

        if ($remaining >= 0.001) {
            $this->apply(
                $ingredient,
                'adjustment',
                $remaining,
                $actor,
                'Cancelled order ' . $order->reference_number,
                $order->id,
            );
        }
    }

    private function apply(
        Ingredient $ingredient,
        string $type,
        float $quantity,
        ?User $actor,
        ?string $note,
        ?int $orderId = null,
        ?string $reason = null,
    ): array
    {
        $delta = match ($type) {
            'stock_in' => abs($quantity),
            'stock_out' => -abs($quantity),
            'prep_out' => -abs($quantity),
            'waste' => -abs($quantity),
            'adjustment' => $quantity,
            default => throw new \DomainException('Unknown stock movement type.'),
        };

        $quantity = round(abs($delta), 3);
        if ($quantity < 0.001) {
            throw new \DomainException('Movement quantity must be at least 0.001.');
        }

        $before = round((float) $ingredient->current_stock, 3);
        $new = round($before + $delta, 3);

        if ($new < -self::EPSILON) {
            throw new \DomainException("Not enough {$ingredient->name} in stock for that movement.");
        }

        $after = max(0.0, $new);
        $movementLots = [];

        if ($delta < 0) {
            $remaining = $quantity;
            $stockAt = $before;
            $lots = InventoryStockLot::query()
                ->where('ingredient_id', $ingredient->id)
                ->where('remaining_quantity', '>', 0)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($remaining < 0.001) {
                    break;
                }

                $used = min($remaining, (float) $lot->remaining_quantity);
                $used = round($used, 3);
                if ($used < 0.001) {
                    continue;
                }

                $lotAfter = round((float) $lot->remaining_quantity - $used, 3);
                $lot->update(['remaining_quantity' => max(0.0, $lotAfter)]);
                $stockAfterLot = round($stockAt - $used, 3);
                $movementLots[] = [
                    'lot_id' => $lot->id,
                    'quantity' => $used,
                    'unit_cost' => $lot->unit_cost ?? $ingredient->unit_cost,
                    'stock_before' => $stockAt,
                    'stock_after' => $stockAfterLot,
                ];
                $stockAt = $stockAfterLot;
                $remaining = round($remaining - $used, 3);
            }

            if ($remaining >= 0.001) {
                throw new \DomainException(
                    "Not enough unexpired stock lots for {$ingredient->name}; reconcile the lot quantities before recording this movement."
                );
            }
        } else {
            $lot = InventoryStockLot::create([
                'ingredient_id' => $ingredient->id,
                'batch_number' => null,
                'expires_at' => null,
                'received_quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost' => $ingredient->unit_cost,
                'receipt_token' => 'movement-'.Str::uuid(),
                'received_by' => $actor?->id,
                'received_at' => now(),
            ]);
            $movementLots[] = [
                'lot_id' => $lot->id,
                'quantity' => $quantity,
                'unit_cost' => $lot->unit_cost,
                'stock_before' => $before,
                'stock_after' => $after,
            ];
        }

        $ingredient->update(['current_stock' => $after]);

        foreach ($movementLots as $movementLot) {
            InventoryLog::create([
                'ingredient_id' => $ingredient->id,
                'stock_lot_id' => $movementLot['lot_id'],
                'user_id' => $actor?->id,
                'order_id' => $orderId,
                'type' => $type,
                'quantity' => in_array($type, ['adjustment', 'waste', 'prep_out'], true)
                    ? ($delta < 0 ? -$movementLot['quantity'] : $movementLot['quantity'])
                    : $movementLot['quantity'],
                'stock_before' => $movementLot['stock_before'],
                'stock_after' => $movementLot['stock_after'],
                'notes' => $note !== null ? mb_substr($note, 0, 500) : null,
                'reason' => $reason,
                'cost_amount' => $type === 'waste' && $movementLot['unit_cost'] !== null
                    ? round($movementLot['quantity'] * (float) $movementLot['unit_cost'], 4)
                    : null,
            ]);
        }

        return $movementLots;
    }

    private function usableLotQuantity(int $ingredientId): float
    {
        return (float) InventoryStockLot::query()
            ->where('ingredient_id', $ingredientId)
            ->where('remaining_quantity', '>', 0)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->sum('remaining_quantity');
    }
}
