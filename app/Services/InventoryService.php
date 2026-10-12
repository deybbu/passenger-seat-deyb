<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderIngredientReservation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
                    + ((float) $ingredient->pivot->quantity * (int) $line->qty);
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
            $available = (float) ($ingredient?->current_stock ?? 0)
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

            $this->apply(
                $ingredient,
                'adjustment',
                (float) $reservation->quantity,
                $actor,
                'Cancelled order ' . $order->reference_number,
                $order->id,
            );
            $reservation->update(['status' => 'restored']);
        }
    }

    /** Manual log from inventory screens. Waste and stock_out quantities are positive inputs. */
    public function logMovement(Ingredient $ingredient, string $type, float $quantity, ?User $actor, ?string $note): void
    {
        DB::transaction(function () use ($ingredient, $type, $quantity, $actor, $note) {
            $locked = Ingredient::lockForUpdate()->findOrFail($ingredient->id);
            $this->assertDoesNotConsumeReservedStock($locked, $type, $quantity);
            $this->apply($locked, $type, $quantity, $actor, $note);
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
            'waste' => -abs($quantity),
            'adjustment' => $quantity,
            default => 0.0,
        };

        if ($delta >= 0.0) {
            return;
        }

        $remaining = (float) $ingredient->current_stock + $delta;
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

    private function apply(
        Ingredient $ingredient,
        string $type,
        float $quantity,
        ?User $actor,
        ?string $note,
        ?int $orderId = null,
    ): void
    {
        $delta = match ($type) {
            'stock_in' => abs($quantity),
            'stock_out' => -abs($quantity),
            'waste' => -abs($quantity),
            'adjustment' => $quantity,
            default => throw new \DomainException('Unknown stock movement type.'),
        };

        $before = round((float) $ingredient->current_stock, 3);
        $new = round($before + $delta, 3);

        if ($new < -self::EPSILON) {
            throw new \DomainException("Not enough {$ingredient->name} in stock for that movement.");
        }

        $after = max(0.0, $new);
        $ingredient->update(['current_stock' => $after]);

        InventoryLog::create([
            'ingredient_id' => $ingredient->id,
            'user_id' => $actor?->id,
            'order_id' => $orderId,
            'type' => $type,
            'quantity' => $type === 'adjustment' || $type === 'waste' ? $delta : abs($quantity),
            'stock_before' => $before,
            'stock_after' => $after,
            'notes' => $note !== null ? mb_substr($note, 0, 500) : null,
        ]);
    }
}
