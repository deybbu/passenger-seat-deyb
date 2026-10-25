<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\InventoryPackage;
use App\Models\InventoryReceipt;
use App\Models\InventoryStockLot;
use App\Models\Order;
use App\Models\OrderIngredientReservation;
use App\Models\PreparationRecipe;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
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
     * @param  Collection<int, object{item: \App\Models\MenuItem, qty: int, variant?: ?\App\Models\MenuItemVariant}>  $lines
     * @return array<int,float> ingredient_id => quantity
     */
    public function requirements(Collection $lines): array
    {
        $needs = [];

        foreach ($lines as $line) {
            foreach ($line->item->ingredients as $ingredient) {
                $needs[$ingredient->id] = ($needs[$ingredient->id] ?? 0.0)
                    + $ingredient->rawQuantityForPortion(
                        (float) $ingredient->pivot->quantity
                            * (isset($line->variant) ? (float) $line->variant->portion_factor : 1.0)
                            * (int) $line->qty,
                    );
            }
        }

        return $needs;
    }

    /** Reserve recipe stock while validating an order before consuming its FIFO lots. */
    public function reserveForOrder(Order $order, Collection $lines): void
    {
        $unconfigured = $lines
            ->filter(fn ($line) => $line->item->ingredients->isEmpty())
            ->map(fn ($line) => $line->item->name)
            ->unique()
            ->values();

        if ($unconfigured->isNotEmpty()) {
            throw new \DomainException(
                'Recipe not configured for: ' . $unconfigured->implode(', ') . '. Please add a recipe before taking this order.'
            );
        }

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

    /** Release pending reservations, restore pre-preparation cancellations, or record prepared stock as waste. */
    public function releaseOrRestoreForCancelledOrder(Order $order, ?User $actor, bool $restoreConsumed = true): void
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

            if (! $restoreConsumed) {
                $logs = InventoryLog::query()
                    ->with('stockLot')
                    ->where('order_id', $order->id)
                    ->where('ingredient_id', $ingredient->id)
                    ->where('type', 'stock_out')
                    ->whereNotNull('stock_lot_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($logs->isEmpty()) {
                    throw new \DomainException(
                        "Stock history for {$reservation->ingredient_name} is missing; the order cannot be cancelled safely."
                    );
                }

                foreach ($logs as $log) {
                    $unitCost = $log->stockLot?->unit_cost ?? $ingredient->unit_cost;
                    $quantity = abs((float) $log->quantity);
                    $log->update([
                        'user_id' => $actor?->id,
                        'type' => 'waste',
                        'quantity' => -$quantity,
                        'notes' => 'Order ' . $order->reference_number . ' cancelled after preparation started.',
                        'reason' => 'Order cancelled after preparation began',
                        'stage' => 'Prep / production',
                        'cost_amount' => $unitCost === null ? null : round($quantity * (float) $unitCost, 4),
                        'created_at' => now(),
                    ]);
                }
                $reservation->update(['status' => 'wasted']);

                continue;
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
            ->where(fn ($query) => $query->where('barcode', $barcode)->orWhere('manual_entry_code', $barcode))
            ->where('is_active', true)
            ->first();

        if ($package !== null) {
            return ['ingredient' => $package->ingredient, 'package' => $package];
        }

        $ingredient = Ingredient::query()->where('barcode', $barcode)->first();

        return $ingredient === null ? null : ['ingredient' => $ingredient, 'package' => null];
    }

    /** Confirm a receipt only for a registered package identifier, using server-side unit conversion. */
    public function receiveRegisteredPackage(
        string $code,
        string $manualCode,
        float $enteredQuantity,
        string $enteredUnit,
        ?float $conversionFactor,
        string $receiptToken,
        string $batchNumber,
        string $expiresAt,
        string $storageZone,
        ?string $scannedAt,
        ?string $manufacturer,
        ?string $serialNumber,
        ?string $storageBin,
        ?int $purchaseOrderItemId,
        User $actor,
    ): array {
        return DB::transaction(function () use (
            $code, $manualCode, $enteredQuantity, $enteredUnit, $conversionFactor,
            $receiptToken, $batchNumber, $expiresAt, $storageZone, $scannedAt,
            $manufacturer, $serialNumber, $storageBin, $purchaseOrderItemId, $actor,
        ): array {
            $package = InventoryPackage::query()
                ->where(fn ($query) => $query->where('barcode', $code)->orWhere('manual_entry_code', $code))
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            if ($package === null || ($manualCode !== $package->manual_entry_code && $manualCode !== $package->barcode)) {
                throw new \DomainException('This code does not match an active registered package.');
            }
            $retry = InventoryReceipt::query()
                ->with(['stockLot.ingredient', 'stockLot.package', 'stockLot.purchaseOrderItem.purchaseOrder'])
                ->where('idempotency_key', $receiptToken)
                ->first();
            if ($retry !== null) {
                if ((int) $retry->package_id !== (int) $package->id || $retry->stockLot === null) {
                    throw new \DomainException('This idempotency key belongs to another receipt.', 409);
                }
                $savedLot = $retry->stockLot;
                $savedIngredient = Ingredient::query()->findOrFail($savedLot->ingredient_id);
                $savedLog = InventoryLog::query()->with('user')->where('stock_lot_id', $savedLot->id)->where('type', 'stock_in')->latest('id')->first();
                $savedLine = $savedLot->purchaseOrderItem;
                return [
                    'ingredient' => $savedIngredient,
                    'lot' => $savedLot,
                    'log' => $savedLog,
                    'current_stock' => max(0, round((float) $savedIngredient->current_stock - (float) $savedLot->received_quantity, 3)),
                    'package_quantity' => (float) $savedLot->received_quantity,
                    'expected_stock' => (float) $savedIngredient->current_stock,
                    'receipt' => $retry,
                    'purchase_order_item' => $savedLine,
                    'purchase_order' => $savedLine?->purchaseOrder,
                    'replayed' => true,
                ];
            }
            if (InventoryReceipt::query()->where('package_id', $package->id)->exists()) {
                $existing = InventoryReceipt::query()->where('package_id', $package->id)->value('received_at');
                throw new \DomainException('This package was already received'.($existing ? ' on '.(string) $existing : '').'.', 409);
            }
            if (InventoryStockLot::query()->where('package_id', $package->id)->exists()) {
                throw new \DomainException('This package already has a recorded receiving lot.', 409);
            }
            if (InventoryReceipt::query()->where('idempotency_key', $receiptToken)->exists()
                || InventoryStockLot::query()->where('receipt_token', $receiptToken)->exists()) {
                throw new \DomainException('This receipt was already confirmed. Scan the package again for a new delivery.', 409);
            }

            $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($package->ingredient_id);
            $quantity = $this->convertInventoryQuantity($enteredQuantity, $enteredUnit, (string) $ingredient->unit, $conversionFactor);

            $orderItem = null;
            $purchaseOrder = null;
            if ($purchaseOrderItemId !== null) {
                $orderItem = PurchaseOrderItem::query()->lockForUpdate()->findOrFail($purchaseOrderItemId);
                $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($orderItem->purchase_order_id);
                if ((int) $orderItem->ingredient_id !== (int) $ingredient->id
                    || ! in_array($purchaseOrder->status, ['open', 'partial'], true)
                    || $purchaseOrder->closed_at !== null
                    || ($purchaseOrder->storage_zone && $purchaseOrder->storage_zone !== $storageZone)) {
                    throw new \DomainException('This purchase order is not open for this package.', 409);
                }
                $balance = round((float) $orderItem->ordered_quantity - (float) $orderItem->received_quantity, 3);
                if ($quantity > $balance + self::EPSILON) {
                    throw new \DomainException('Over-delivery: the received quantity cannot exceed the '.number_format(max(0, $balance), 3).' remaining on this purchase order.');
                }
            }

            $scanTime = $scannedAt
                ? \Illuminate\Support\Carbon::parse($scannedAt)->timezone(config('app.timezone', 'Asia/Manila'))
                : now();
            $receivedAt = now();
            $receipt = InventoryReceipt::query()->create([
                'package_id' => $package->id,
                'idempotency_key' => $receiptToken,
                'status' => 'received',
                'scanned_by' => $actor->id,
                'scanned_at' => $scanTime,
                'received_by' => $actor->id,
                'received_at' => $receivedAt,
            ]);

            $recorded = $this->recordPackageReceipt(
                $ingredient,
                $package,
                $quantity,
                $package->barcode,
                $receiptToken,
                $batchNumber,
                $expiresAt,
                $storageZone,
                $actor,
                $ingredient->unit_cost === null ? null : (float) $ingredient->unit_cost,
                false,
                $orderItem?->id,
                null,
                "Scanned at {$scanTime->toIso8601String()}; received at {$receivedAt->toIso8601String()}; package {$package->barcode}; batch {$batchNumber}; expires {$expiresAt}; stored in {$storageZone}.",
                $receipt->id,
                $scanTime->toDateTimeString(),
                $manufacturer,
                $serialNumber,
                $storageBin,
            );

            if ($orderItem !== null && $purchaseOrder !== null) {
                $orderItem->update(['received_quantity' => round((float) $orderItem->received_quantity + $quantity, 3)]);
                $orderItems = PurchaseOrderItem::query()->where('purchase_order_id', $purchaseOrder->id)->get(['ordered_quantity', 'received_quantity']);
                $complete = $orderItems->isNotEmpty() && $orderItems->every(fn (PurchaseOrderItem $line): bool => (float) $line->received_quantity >= (float) $line->ordered_quantity);
                $anyReceived = $orderItems->contains(fn (PurchaseOrderItem $line): bool => (float) $line->received_quantity > 0);
                $purchaseOrder->update([
                    'status' => $complete ? 'completed' : ($anyReceived ? 'partial' : 'open'),
                    'closed_at' => $complete ? now() : null,
                ]);
                $orderItem->refresh();
                $purchaseOrder->refresh();
            }

            return [...$recorded, 'receipt' => $receipt, 'purchase_order_item' => $orderItem, 'purchase_order' => $purchaseOrder];
        });
    }

    private function convertInventoryQuantity(float $quantity, string $fromUnit, string $toUnit, ?float $factor): float
    {
        if ($quantity <= 0) {
            throw new \DomainException('Received quantity must be greater than zero.');
        }
        if ($fromUnit === $toUnit) {
            return round($quantity, 3);
        }
        $groups = [
            'kg' => ['kg' => 1.0, 'g' => 0.001], 'g' => ['kg' => 1000.0, 'g' => 1.0],
            'L' => ['L' => 1.0, 'mL' => 0.001], 'mL' => ['L' => 1000.0, 'mL' => 1.0],
            'pc' => ['pc' => 1.0, 'pcs' => 1.0, 'can' => 1.0],
            'pcs' => ['pc' => 1.0, 'pcs' => 1.0, 'can' => 1.0],
            'can' => ['pc' => 1.0, 'pcs' => 1.0, 'can' => 1.0],
        ];
        if (isset($groups[$fromUnit][$toUnit])) {
            return round($quantity * $groups[$fromUnit][$toUnit], 3);
        }
        if (in_array($fromUnit, ['pack / bag', 'pack', 'bag'], true) && $factor !== null && $factor > 0) {
            return round($quantity * $factor, 3);
        }
        throw new \DomainException("Cannot convert {$fromUnit} to {$toUnit}; provide a pack base-unit factor when receiving packs or bags.");
    }

    public function convertReceiptQuantity(float $quantity, string $fromUnit, string $toUnit, ?float $factor): float
    {
        return $this->convertInventoryQuantity($quantity, $fromUnit, $toUnit, $factor);
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
                ->where(fn ($query) => $query->where('barcode', $barcode)->orWhere('manual_entry_code', $barcode))
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            $ingredient = $package !== null
                ? Ingredient::query()->lockForUpdate()->find($package->ingredient_id)
                : Ingredient::query()->where('barcode', $barcode)->lockForUpdate()->first();

            if ($ingredient === null) {
                throw new \DomainException('This barcode is not registered to an active package or ingredient.');
            }
            if ($package !== null && (InventoryReceipt::query()->where('package_id', $package->id)->exists()
                || InventoryStockLot::query()->where('package_id', $package->id)->exists())) {
                throw new \DomainException('This package has already been received.', 409);
            }

            $quantity = $package !== null ? (float) $package->package_quantity : $manualQuantity;

            if ($quantity === null || $quantity <= 0) {
                throw new \DomainException('Enter a package quantity before confirming this receipt.');
            }

            return $this->recordPackageReceipt(
                $ingredient,
                $package,
                round($quantity, 3),
                $package?->barcode ?? $barcode,
                $receiptToken,
                $batchNumber,
                $expiresAt,
                $storageZone,
                $actor,
                $ingredient->unit_cost === null ? null : (float) $ingredient->unit_cost,
                false,
                null,
                null,
                "Received barcode {$barcode}; batch {$batchNumber}; expires {$expiresAt}; stored in {$storageZone}.",
            );
        });
    }

    /** Receive a barcode-matched delivery against its open purchase-order line. */
    public function receivePurchaseOrderPackage(
        string $barcode,
        int $purchaseOrderItemId,
        float $quantity,
        ?float $invoiceUnitCost,
        string $invoiceNumber,
        string $batchNumber,
        string $expiresAt,
        ?string $storageZone,
        string $receiptToken,
        User $actor,
    ): array {
        return DB::transaction(function () use (
            $barcode,
            $purchaseOrderItemId,
            $quantity,
            $invoiceUnitCost,
            $invoiceNumber,
            $batchNumber,
            $expiresAt,
            $storageZone,
            $receiptToken,
            $actor,
        ): array {
            if (InventoryStockLot::query()->where('receipt_token', $receiptToken)->exists()) {
                throw new \DomainException('This receipt was already confirmed. Scan the package again for a new delivery.', 409);
            }

            $orderItem = PurchaseOrderItem::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrderItemId);
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($orderItem->purchase_order_id);
            if (! in_array($purchaseOrder->status, ['open', 'partial'], true) || $purchaseOrder->closed_at !== null) {
                throw new \DomainException('This purchase order is no longer open for receiving.', 409);
            }

            $package = InventoryPackage::query()
                ->where(fn ($query) => $query->where('barcode', $barcode)->orWhere('manual_entry_code', $barcode))
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            $ingredient = $package !== null
                ? Ingredient::query()->lockForUpdate()->find($package->ingredient_id)
                : Ingredient::query()->where('barcode', $barcode)->lockForUpdate()->first();

            if ($ingredient === null) {
                throw new \DomainException('This barcode is not registered to an active package or ingredient.');
            }
            if ($package === null) {
                throw new \DomainException('Purchase-order receiving requires a registered package QR code.');
            }
            if ((int) $orderItem->ingredient_id !== (int) $ingredient->id) {
                throw new \DomainException('The scanned item does not match this purchase-order line.');
            }
            if (InventoryReceipt::query()->where('package_id', $package->id)->exists()
                || InventoryStockLot::query()->where('package_id', $package->id)->exists()) {
                throw new \DomainException('This package has already been received.', 409);
            }
            if (InventoryReceipt::query()->where('idempotency_key', $receiptToken)->exists()) {
                throw new \DomainException('This receipt was already confirmed.', 409);
            }

            $remaining = round((float) $orderItem->ordered_quantity - (float) $orderItem->received_quantity, 3);
            $quantity = round($quantity, 3);
            if ($remaining < 0.001) {
                throw new \DomainException('This purchase-order line has already been received.', 409);
            }
            if ($quantity < 0.001 || $quantity > $remaining + self::EPSILON) {
                throw new \DomainException(
                    'Over-delivery: the received quantity cannot exceed the '.number_format($remaining, 3).' remaining on this purchase order.'
                );
            }
            if (trim($invoiceNumber) === '' || trim($batchNumber) === '') {
                throw new \DomainException('Invoice number and batch code are required.');
            }

            $unitCost = $invoiceUnitCost === null
                ? ($ingredient->unit_cost === null ? null : (float) $ingredient->unit_cost)
                : round($invoiceUnitCost, 4);
            $receiptRecord = InventoryReceipt::query()->create([
                'package_id' => $package->id,
                'idempotency_key' => $receiptToken,
                'status' => 'received',
                'scanned_by' => $actor->id,
                'scanned_at' => now(),
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);
            $receipt = $this->recordPackageReceipt(
                $ingredient,
                $package,
                $quantity,
                $package->barcode,
                $receiptToken,
                trim($batchNumber),
                $expiresAt,
                $storageZone,
                $actor,
                $unitCost,
                $invoiceUnitCost !== null,
                $orderItem->id,
                trim($invoiceNumber),
                "Received barcode {$barcode} against {$purchaseOrder->reference}; invoice {$invoiceNumber}; batch {$batchNumber}; expires {$expiresAt}.",
                $receiptRecord->id,
                $receiptRecord->scanned_at?->toDateTimeString(),
            );

            $orderItem->update([
                'received_quantity' => round((float) $orderItem->received_quantity + $quantity, 3),
            ]);
            $items = PurchaseOrderItem::query()
                ->where('purchase_order_id', $purchaseOrder->id)
                ->get(['ordered_quantity', 'received_quantity']);
            $complete = $items->isNotEmpty() && $items->every(
                fn (PurchaseOrderItem $item): bool => (float) $item->received_quantity >= (float) $item->ordered_quantity,
            );
            $hasReceipts = $items->contains(fn (PurchaseOrderItem $item): bool => (float) $item->received_quantity > 0);
            $purchaseOrder->update([
                'invoice_number' => trim($invoiceNumber),
                'status' => $complete ? 'completed' : ($hasReceipts ? 'partial' : 'open'),
                'closed_at' => $complete ? now() : null,
            ]);

            return [
                ...$receipt,
                'purchase_order' => $purchaseOrder,
                'received_quantity' => $quantity,
                'remaining_quantity' => round($remaining - $quantity, 3),
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
        ?string $stage = null,
    ): ?float {
        return DB::transaction(function () use ($ingredient, $quantity, $reason, $details, $actor, $stage): ?float {
            if (trim($reason) === '' || trim($details) === '') {
                throw new \DomainException('Waste requires a reason and details.');
            }

            $locked = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);
            $this->assertDoesNotConsumeReservedStock($locked, 'waste', $quantity);
            $movementLots = $this->apply($locked, 'waste', $quantity, $actor, trim($details), null, $reason, $stage);
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

    /**
     * @return array{ingredient: Ingredient, lot: InventoryStockLot, current_stock: float, package_quantity: float, expected_stock: float}
     */
    private function recordPackageReceipt(
        Ingredient $ingredient,
        ?InventoryPackage $package,
        float $quantity,
        string $barcode,
        string $receiptToken,
        string $batchNumber,
        string $expiresAt,
        ?string $storageZone,
        User $actor,
        ?float $unitCost,
        bool $updateUnitCost,
        ?int $purchaseOrderItemId,
        ?string $invoiceNumber,
        string $notes,
        ?int $receiptId = null,
        ?string $scannedAt = null,
        ?string $manufacturer = null,
        ?string $serialNumber = null,
        ?string $storageBin = null,
    ): array {
        $quantity = round($quantity, 3);
        $before = round((float) $ingredient->current_stock, 3);
        $after = round($before + $quantity, 3);
        $lotUnitCost = $updateUnitCost
            ? $unitCost
            : ($ingredient->unit_cost === null ? null : (float) $ingredient->unit_cost);
        $ingredientChanges = ['current_stock' => $after];
        if ($updateUnitCost) {
            $ingredientChanges['unit_cost'] = $unitCost;
        }
        $ingredient->update($ingredientChanges);

        $lot = InventoryStockLot::create([
            'ingredient_id' => $ingredient->id,
            'package_id' => $package?->id,
            'batch_number' => trim($batchNumber),
            'expires_at' => $expiresAt,
            'received_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost' => $lotUnitCost,
            'receipt_token' => $receiptToken,
            'receipt_id' => $receiptId,
            'received_by' => $actor->id,
            'received_at' => now(),
            'scanned_at' => $scannedAt,
            'storage_zone' => $storageZone !== null && trim($storageZone) !== '' ? trim($storageZone) : null,
            'manufacturer' => $manufacturer !== null ? mb_substr(trim($manufacturer), 0, 120) : null,
            'serial_number' => $serialNumber !== null ? mb_substr(trim($serialNumber), 0, 120) : null,
            'storage_bin' => $storageBin !== null ? mb_substr(trim($storageBin), 0, 80) : null,
            'purchase_order_item_id' => $purchaseOrderItemId,
            'supplier_invoice_number' => $invoiceNumber,
        ]);

        $log = InventoryLog::create([
            'ingredient_id' => $ingredient->id,
            'stock_lot_id' => $lot->id,
            'user_id' => $actor->id,
            'type' => 'stock_in',
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $after,
            'notes' => mb_substr($notes, 0, 500),
        ]);

        return [
            'ingredient' => $ingredient,
            'lot' => $lot,
            'log' => $log,
            'current_stock' => $before,
            'package_quantity' => $quantity,
            'expected_stock' => $after,
        ];
    }

    private function apply(
        Ingredient $ingredient,
        string $type,
        float $quantity,
        ?User $actor,
        ?string $note,
        ?int $orderId = null,
        ?string $reason = null,
        ?string $stage = null,
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
                ->orderBy('received_at')
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
                'stage' => $stage,
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
