<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosTableSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $orders = $this->orders->reject(fn (Order $order): bool => $order->status === 'cancelled');
        $items = $orders->flatMap(function (Order $order): array {
            return $order->items
                ->whereNull('cancelled_at')
                ->map(fn (OrderItem $item): array => [
                    'order_item_id' => (int) $item->id,
                    'n' => (string) $item->item_name,
                    'p' => (float) $item->price,
                    'd' => 'k',
                    'q' => (int) $item->quantity,
                    'm' => collect($item->modifier_snapshot)->pluck('name')->filter()->values()->all(),
                    'seat' => $item->seat_number === null ? '' : (string) $item->seat_number,
                    'sent' => true,
                    'b' => (int) ($order->batch_number ?: 1),
                    'at' => $order->created_at?->copy()->timezone(config('app.timezone'))->format('g:i A') ?? '',
                    'src' => match ($order->source) {
                        'table_qr' => 'QR',
                        'cashier' => 'Cashier',
                        'website' => 'Website',
                        default => null,
                    },
                    'cashier_name' => $order->source === 'cashier' ? (string) ($order->creator?->name ?? '') : null,
                ])
                ->all();
        })->values()->all();
        $requestedMethod = match ($this->requested_payment_method) {
            'gcash' => 'GCash',
            'card' => 'Card',
            'cash' => 'Cash',
            default => null,
        };

        return [
            'id' => (int) $this->id,
            'label' => 'Table '.(string) $this->table_number,
            'items' => $items,
            'st' => 'open',
            'req' => $this->status === 'bill_requested' || $this->bill_requested_at !== null,
            'requestedPayMethod' => $requestedMethod,
            'bills' => ($this->relationLoaded('bills') ? $this->bills : collect())->map(function ($bill): array {
                $payment = $bill->payment;

                return [
                    'id' => (int) $bill->id,
                    'name' => (string) ($bill->display_name ?: 'Bill '.$bill->bill_number),
                    'amt' => (float) $bill->total,
                    'lines' => $bill->split_method === 'even'
                        ? 'Equal share'
                        : $bill->items->map(fn ($line): string => $line->quantity.' × '.$line->orderItem->item_name)
                            ->filter()->implode(', '),
                    'paid' => $bill->status === 'paid',
                    'pay' => $payment === null ? null : [
                        'm' => match ($payment->payment_method) {
                            'gcash' => 'GCash', 'card' => 'Card', default => 'Cash',
                        },
                        'ref' => (string) ($payment->reference_number ?? ''),
                        'change' => $payment->change_given === null ? null : (float) $payment->change_given,
                        'reference' => (string) $payment->payment_reference,
                    ],
                ];
            })->values()->all(),
            'bn' => max(1, (int) $orders->max('batch_number')),
            'view' => 'bill',
        ];
    }
}
