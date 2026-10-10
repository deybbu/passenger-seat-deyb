<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MenuItem;
use Illuminate\Support\Collection;

/**
 * Session cart for customers. Stores only [menu_item_id => quantity].
 * Prices are always read from the database, never from the browser.
 */
final class CartService
{
    public const MAX_QTY = 20;

    /** @return array<int,int> */
    private function raw(): array
    {
        return session('cart', []);
    }

    public function count(): int
    {
        return (int) array_sum($this->raw());
    }

    /** @return Collection<int, object{item: MenuItem, qty: int, line_total: float}> */
    public function lines(): Collection
    {
        $cart = $this->raw();

        if ($cart === []) {
            return collect();
        }

        $items = MenuItem::with('ingredients')->whereIn('id', array_keys($cart))->get()->keyBy('id');

        return collect($cart)
            ->map(function ($qty, $id) use ($items) {
                $item = $items->get($id);

                if ($item === null) {
                    return null;
                }

                $qty = (int) $qty;

                return (object) [
                    'item' => $item,
                    'qty' => $qty,
                    'line_total' => round((float) $item->price * $qty, 2),
                ];
            })
            ->filter()
            ->values();
    }

    public function total(?Collection $lines = null): float
    {
        return round((float) ($lines ?? $this->lines())->sum('line_total'), 2);
    }

    public function add(int $id, int $qty): void
    {
        $cart = $this->raw();
        $cart[$id] = min(self::MAX_QTY, ($cart[$id] ?? 0) + $qty);
        session(['cart' => $cart]);
    }

    public function set(int $id, int $qty): void
    {
        $cart = $this->raw();

        if ($qty <= 0) {
            unset($cart[$id]);
        } else {
            $cart[$id] = min(self::MAX_QTY, $qty);
        }

        session(['cart' => $cart]);
    }

    public function remove(int $id): void
    {
        $this->set($id, 0);
    }

    public function clear(): void
    {
        session()->forget('cart');
    }
}
