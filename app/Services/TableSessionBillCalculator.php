<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;

final class TableSessionBillCalculator
{
    /**
     * @return array{
     *     status:string,
     *     order_count:int,
     *     subtotal:float,
     *     discount:float,
     *     vat_exemption:float,
     *     vat:float,
     *     total:float,
     *     requested_payment_method:?string,
     *     bill_requested_at:?string,
     *     orders:array<int, array<string, mixed>>
     * }|null
     */
    public function refresh(TableSession $session): ?array
    {
        return DB::transaction(function () use ($session): ?array {
            $session = TableSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($session->closed_at !== null || ! in_array($session->status, ['open', 'bill_requested'], true)) {
                return null;
            }

            $orders = $session->orders()
                ->with(['items' => fn ($query) => $query->whereNull('cancelled_at')])
                ->orderBy('batch_number')
                ->orderBy('id')
                ->get();

            $billableOrders = $orders->reject(fn (Order $order): bool => $order->status === 'cancelled');
            $subtotal = round((float) $billableOrders->sum(
                fn (Order $order): float => $order->items->sum(
                    fn (OrderItem $item): float => (float) $item->price * (int) $item->quantity,
                ),
            ), 2);
            $vat = round($subtotal * 12 / 112, 2);

            $session->update([
                'subtotal' => $subtotal,
                'discount' => 0,
                'vat_exemption' => 0,
                'vat' => $vat,
                'total' => $subtotal,
            ]);

            return [
                'status' => $session->status,
                'order_count' => $billableOrders->count(),
                'subtotal' => $subtotal,
                'discount' => 0.0,
                'vat_exemption' => 0.0,
                'vat' => $vat,
                'total' => $subtotal,
                'requested_payment_method' => $session->requested_payment_method,
                'bill_requested_at' => $session->bill_requested_at?->toIso8601String(),
                'orders' => $orders->map(fn (Order $order): array => [
                    'reference' => $order->reference_number,
                    'batch_number' => (int) $order->batch_number,
                    'status' => $order->status,
                    'total' => $order->status === 'cancelled' ? 0.0 : round((float) $order->items->sum(
                        fn (OrderItem $item): float => (float) $item->price * (int) $item->quantity,
                    ), 2),
                    'special_instructions' => $order->special_instructions,
                    'created_at' => $order->created_at?->toIso8601String(),
                    'items' => $order->items->map(fn (OrderItem $item): array => [
                        'name' => $item->item_name,
                        'quantity' => (int) $item->quantity,
                        'variant' => $item->variant_name_snapshot,
                        'modifiers' => collect($item->modifier_snapshot)->pluck('name')->filter()->values()->all(),
                    ])->all(),
                ])->all(),
            ];
        }, 3);
    }
}
