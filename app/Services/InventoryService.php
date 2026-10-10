<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\Order;
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

    /** Call inside a transaction. Locks the ingredient rows it checks. */
    public function assertAvailable(Collection $lines): void
    {
        $off = $lines->filter(fn ($l) => ! $l->item->is_available)->map(fn ($l) => $l->item->name);

        if ($off->isNotEmpty()) {
            throw new \DomainException('Not available right now: ' . $off->implode(', ') . '. Please remove it and try again.');
        }

        $needs = $this->requirements($lines);

        if ($needs === []) {
            return;
        }

        $stock = Ingredient::whereIn('id', array_keys($needs))->lockForUpdate()->get()->keyBy('id');
        $short = [];

        foreach ($lines as $line) {
            foreach ($line->item->ingredients as $ingredient) {
                $have = (float) ($stock->get($ingredient->id)?->current_stock ?? 0);

                if ($have + self::EPSILON < $needs[$ingredient->id]) {
                    $short[$line->item->name] = true;
                }
            }
        }

        if ($short !== []) {
            throw new \DomainException(
                'Sorry, we do not have enough stock for: ' . implode(', ', array_keys($short)) . '. Please change your order.'
            );
        }
    }

    public function deductForOrder(Order $order, Collection $lines, ?User $actor): void
    {
        foreach ($this->requirements($lines) as $ingredientId => $quantity) {
            $ingredient = Ingredient::lockForUpdate()->findOrFail($ingredientId);
            $this->apply($ingredient, 'stock_out', $quantity, $actor, 'Order ' . $order->reference_number);
        }
    }

    /** Puts stock back when an order is cancelled. */
    public function restoreForOrder(Order $order, ?User $actor): void
    {
        $order->loadMissing('items.menuItem.ingredients');
        $needs = [];

        foreach ($order->items as $orderItem) {
            if ($orderItem->menuItem === null) {
                continue;
            }

            foreach ($orderItem->menuItem->ingredients as $ingredient) {
                $needs[$ingredient->id] = ($needs[$ingredient->id] ?? 0.0)
                    + ((float) $ingredient->pivot->quantity * (int) $orderItem->quantity);
            }
        }

        foreach ($needs as $ingredientId => $quantity) {
            $ingredient = Ingredient::lockForUpdate()->findOrFail($ingredientId);
            $this->apply($ingredient, 'adjustment', $quantity, $actor, 'Cancelled order ' . $order->reference_number);
        }
    }

    /** Manual log from the inventory screen. stock_in / stock_out take a positive quantity; adjustment is signed. */
    public function logMovement(Ingredient $ingredient, string $type, float $quantity, ?User $actor, ?string $note): void
    {
        DB::transaction(function () use ($ingredient, $type, $quantity, $actor, $note) {
            $locked = Ingredient::lockForUpdate()->findOrFail($ingredient->id);
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

            $this->apply($locked, 'adjustment', $delta, $actor, 'Override: ' . $note);
        });
    }

    private function apply(Ingredient $ingredient, string $type, float $quantity, ?User $actor, ?string $note): void
    {
        $delta = match ($type) {
            'stock_in' => abs($quantity),
            'stock_out' => -abs($quantity),
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
            'type' => $type,
            'quantity' => $type === 'adjustment' ? $delta : abs($quantity),
            'stock_before' => $before,
            'stock_after' => $after,
            'notes' => $note !== null ? mb_substr($note, 0, 500) : null,
        ]);
    }
}
