<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderPaymentService
{
    /**
     * @param array{
     *     payment_method:string,
     *     discount_type?:?string,
     *     discount_rate?:numeric-string|float|int|null,
     *     discount_cardholder_name?:string|null,
     *     discount_id_number?:string|null,
     *     discount_tin?:string|null,
     *     amount_tendered?:numeric-string|float|int|null
     * } $data
     */
    public function record(Order $order, array $data, User $cashier): Order
    {
        return DB::transaction(function () use ($order, $data, $cashier): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_if($locked->status === 'cancelled', 422, 'Cancelled orders cannot be paid.');
            abort_if($locked->payment_status !== 'unpaid', 409, 'This order has already been paid or is not payable.');

            $items = OrderItem::query()
                ->where('order_id', $locked->id)
                ->lockForUpdate()
                ->get();
            abort_if($items->isEmpty(), 409, 'This order has no items to pay for.');

            $subtotal = round((float) $items->sum(
                fn (OrderItem $item): float => (float) $item->price * (int) $item->quantity,
            ), 2);
            $discountType = $data['discount_type'] ?? null;
            $discountRate = null;
            $discountAmount = 0.0;
            $total = $subtotal;

            if (in_array($discountType, ['SC', 'PWD'], true)) {
                $discountRate = 20.0;
                $vatExclusive = round($subtotal / 1.12, 2);
                $discountAmount = round($vatExclusive * 0.20, 2);
                $total = round($vatExclusive - $discountAmount, 2);
            } elseif ($discountType === 'CUSTOM') {
                $discountRate = (float) $data['discount_rate'];
                $discountAmount = round($subtotal * $discountRate / 100, 2);
                $total = round($subtotal - $discountAmount, 2);
            }

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'discount_rate' => 'The discount must leave a total greater than zero.',
                ]);
            }

            $cashTendered = $data['payment_method'] === 'cash'
                ? round((float) $data['amount_tendered'], 2)
                : $total;

            if ($data['payment_method'] === 'cash' && $cashTendered < $total) {
                throw ValidationException::withMessages([
                    'amount_tendered' => 'Cash tendered must be at least the order total.',
                ]);
            }

            $hasIdDiscount = in_array($discountType, ['SC', 'PWD'], true);
            $locked->update([
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_rate' => $discountRate,
                'discount_amount' => $discountAmount,
                'discount_cardholder_name' => $hasIdDiscount ? trim($data['discount_cardholder_name']) : null,
                'discount_id_number' => $hasIdDiscount ? trim($data['discount_id_number']) : null,
                'discount_tin' => $hasIdDiscount && isset($data['discount_tin'])
                    ? trim($data['discount_tin'])
                    : null,
                'total' => $total,
                'total_amount' => $total,
                'payment_status' => 'paid',
                'payment_method' => $data['payment_method'],
                'amount_tendered' => $cashTendered,
                'paid_at' => now(),
                'cashier_id' => $cashier->id,
            ]);

            return $locked;
        }, 3);
    }
}
