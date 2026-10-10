<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single place where orders are created and moved between statuses.
 * Totals are always computed here from database prices.
 */
final class OrderService
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /**
     * @param  array{type:string, table_number:?string, customer_name:string, customer_phone:?string, special_instructions:?string}  $data
     * @param  Collection<int, object{item: \App\Models\MenuItem, qty: int, line_total: float}>  $lines
     *
     * @throws \DomainException when stock or availability fails
     */
    public function place(array $data, Collection $lines, ?User $staff = null): Order
    {
        if ($lines->isEmpty()) {
            throw new \DomainException('The order is empty.');
        }

        return DB::transaction(function () use ($data, $lines, $staff) {
            $this->inventory->assertAvailable($lines);

            $orderAttributes = [
                'reference_number' => $this->nextReference(),
                'type' => $data['type'],
                'table_number' => $data['type'] === 'dine_in' ? $data['table_number'] : null,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'special_instructions' => $data['special_instructions'] ?? null,
                'total_amount' => round((float) $lines->sum('line_total'), 2),
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ];

            if ($staff !== null && Schema::hasColumn('orders', 'cashier_id')) {
                $orderAttributes['cashier_id'] = $staff->id;
            }
            if (Schema::hasColumn('orders', 'order_type')) {
                $orderAttributes['order_type'] = $data['type'];
            }
            if (Schema::hasColumn('orders', 'subtotal')) {
                $orderAttributes['subtotal'] = round((float) $lines->sum('line_total'), 2);
            }
            if (Schema::hasColumn('orders', 'total')) {
                $orderAttributes['total'] = round((float) $lines->sum('line_total'), 2);
            }

            $order = Order::create($orderAttributes);

            foreach ($lines as $line) {
                $itemAttributes = [
                    'menu_item_id' => $line->item->id,
                    'name' => $line->item->name,
                    'quantity' => $line->qty,
                    'price' => $line->item->price,
                ];

                if (Schema::hasColumn('order_items', 'line_total')) {
                    $itemAttributes['line_total'] = round((float) $line->line_total, 2);
                }

                $order->items()->create($itemAttributes);
            }

            $this->inventory->deductForOrder($order, $lines, $staff);

            return $order;
        });
    }

    /** @throws \DomainException */
    public function transition(Order $order, string $to, User $actor): Order
    {
        return DB::transaction(function () use ($order, $to, $actor) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if (! $locked->canTransitionTo($to)) {
                throw new \DomainException("Order {$locked->reference_number} cannot go from {$locked->status} to {$to}.");
            }

            if ($to === 'cancelled') {
                if ($locked->isPaid()) {
                    throw new \DomainException('This order is already paid. Handle the refund first.');
                }

                $this->inventory->restoreForOrder($locked, $actor);
            }

            $locked->update(['status' => $to]);

            return $locked;
        });
    }

    /** PSR-2026-0001, counting up each year. */
    private function nextReference(): string
    {
        $year = (int) now()->year;
        $count = Order::whereYear('created_at', $year)->lockForUpdate()->count();

        do {
            $count++;
            $reference = sprintf('PSR-%d-%04d', $year, $count);
        } while (Order::where('reference_number', $reference)->exists());

        return $reference;
    }
}
