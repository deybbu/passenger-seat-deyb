<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
            abort_if(
                $locked->table_session_id !== null,
                409,
                'Table QR orders must be paid together through their table session bill.',
            );
            abort_if($locked->held_at !== null, 409, 'Held orders cannot be paid.');
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
            $isTableQr = $locked->source === 'table_qr';
            $totals = $this->calculateTotals(
                $subtotal,
                $data,
                $isTableQr,
                (float) ($locked->vat_amount ?? 0),
            );
            $discountType = $totals['discountType'];
            $discountRate = $totals['discountRate'];
            $discountAmount = $totals['discountAmount'];
            $vatAmount = $totals['vatAmount'];
            $total = $totals['total'];

            $cashTendered = $data['payment_method'] === 'cash'
                ? round((float) $data['amount_tendered'], 2)
                : $total;

            if ($data['payment_method'] === 'cash' && $cashTendered < $total) {
                throw ValidationException::withMessages([
                    'amount_tendered' => 'Cash tendered must be at least the order total.',
                ]);
            }

            $hasIdDiscount = in_array($discountType, ['SC', 'PWD'], true);
            $updates = [
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
            ];
            if (Schema::hasColumn('orders', 'vat_amount')) {
                $updates['vat_amount'] = $vatAmount;
            }
            $locked->update($updates);

            return $locked;
        }, 3);
    }

    /**
     * Settle every currently unpaid Table QR order for one table as one cashier action.
     *
     * @param array<string, mixed> $data
     * @return Collection<int, Order>
     */
    public function recordTableBill(RestaurantTable $table, array $data, User $cashier): Collection
    {
        return DB::transaction(function () use ($table, $data, $cashier): Collection {
            $lockedTable = RestaurantTable::query()->lockForUpdate()->findOrFail($table->id);
            $orders = Order::query()
                ->notHeld()
                ->where('table_id', $lockedTable->id)
                ->where('source', 'table_qr')
                ->whereNull('table_session_id')
                ->where('payment_status', 'unpaid')
                ->where('status', '!=', 'cancelled')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            abort_if($orders->isEmpty(), 409, 'There are no unpaid Table QR orders for this table.');

            $itemsByOrder = OrderItem::query()
                ->whereIn('order_id', $orders->modelKeys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('order_id');
            abort_if(
                $orders->contains(fn (Order $order): bool => $itemsByOrder->get($order->id, collect())->isEmpty()),
                409,
                'One or more table orders have no items to pay for.',
            );

            $subtotals = $orders->mapWithKeys(fn (Order $order): array => [
                $order->id => round((float) $itemsByOrder->get($order->id)->sum(
                    fn (OrderItem $item): float => (float) $item->price * (int) $item->quantity,
                ), 2),
            ])->all();
            $subtotal = round(array_sum($subtotals), 2);
            $totals = $this->calculateTotals($subtotal, $data, true);
            $cashTendered = $data['payment_method'] === 'cash'
                ? round((float) $data['amount_tendered'], 2)
                : $totals['total'];

            if ($data['payment_method'] === 'cash' && $cashTendered < $totals['total']) {
                throw ValidationException::withMessages([
                    'amount_tendered' => 'Cash tendered must be at least the table bill total.',
                ]);
            }

            $discounts = $this->allocateAmount($totals['discountAmount'], $subtotals);
            $taxableByOrder = [];
            foreach ($subtotals as $orderId => $orderSubtotal) {
                $taxableByOrder[$orderId] = round($orderSubtotal - $discounts[$orderId], 2);
            }
            $vatByOrder = $this->allocateAmount($totals['vatAmount'], $taxableByOrder);
            $totalsByOrder = [];
            foreach ($subtotals as $orderId => $orderSubtotal) {
                $totalsByOrder[$orderId] = round(
                    $orderSubtotal - $discounts[$orderId] + $vatByOrder[$orderId],
                    2,
                );
            }
            $tenderedByOrder = $this->allocateAmount(
                $cashTendered,
                $data['payment_method'] === 'cash' ? $totalsByOrder : $subtotals,
            );
            $hasIdDiscount = in_array($totals['discountType'], ['SC', 'PWD'], true);
            $paymentReference = 'TABLE-'.Str::upper(Str::random(12));
            $paidAt = now();

            foreach ($orders as $order) {
                $orderSubtotal = $subtotals[$order->id];
                $updates = [
                    'subtotal' => $orderSubtotal,
                    'discount_type' => $totals['discountType'],
                    'discount_rate' => $totals['discountRate'],
                    'discount_amount' => $discounts[$order->id],
                    'discount_cardholder_name' => $hasIdDiscount ? trim($data['discount_cardholder_name']) : null,
                    'discount_id_number' => $hasIdDiscount ? trim($data['discount_id_number']) : null,
                    'discount_tin' => $hasIdDiscount && isset($data['discount_tin'])
                        ? trim($data['discount_tin'])
                        : null,
                    'vat_amount' => $vatByOrder[$order->id],
                    'total' => $totalsByOrder[$order->id],
                    'total_amount' => $totalsByOrder[$order->id],
                    'payment_status' => 'paid',
                    'payment_method' => $data['payment_method'],
                    'amount_tendered' => $tenderedByOrder[$order->id],
                    'paid_at' => $paidAt,
                    'cashier_id' => $cashier->id,
                ];
                if (Schema::hasColumn('orders', 'payment_reference')) {
                    $updates['payment_reference'] = $paymentReference;
                }
                if (! Schema::hasColumn('orders', 'vat_amount')) {
                    unset($updates['vat_amount']);
                }
                $order->update($updates);
            }

            return $orders->fresh();
        }, 3);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{discountType: ?string, discountRate: ?float, discountAmount: float, vatAmount: float, total: float}
     */
    private function calculateTotals(
        float $subtotal,
        array $data,
        bool $isTableQr,
        float $existingVatAmount = 0.0,
    ): array {
        $discountType = $data['discount_type'] ?? null;
        $discountRate = null;
        $discountAmount = 0.0;
        $vatAmount = $isTableQr ? round($subtotal * 0.12, 2) : $existingVatAmount;
        $total = $isTableQr
            ? round($subtotal + $vatAmount, 2)
            : $subtotal;

        if (in_array($discountType, ['SC', 'PWD'], true)) {
            $discountRate = 20.0;
            if ($isTableQr) {
                $discountAmount = round($subtotal * 0.20, 2);
                $vatAmount = 0.0;
                $total = round($subtotal - $discountAmount, 2);
            } else {
                $vatExclusive = round($subtotal / 1.12, 2);
                $discountAmount = round($vatExclusive * 0.20, 2);
                $total = round($vatExclusive - $discountAmount, 2);
            }
        } elseif ($discountType === 'CUSTOM') {
            $discountRate = (float) $data['discount_rate'];
            $discountAmount = round($subtotal * $discountRate / 100, 2);
            $taxableAmount = round($subtotal - $discountAmount, 2);
            if ($isTableQr) {
                $vatAmount = round($taxableAmount * 0.12, 2);
                $total = round($taxableAmount + $vatAmount, 2);
            } else {
                $total = $taxableAmount;
            }
        }

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'discount_rate' => 'The discount must leave a total greater than zero.',
            ]);
        }

        return compact('discountType', 'discountRate', 'discountAmount', 'vatAmount', 'total');
    }

    /**
     * Allocate a bill amount in cents while preserving its exact total.
     *
     * @param array<int, float> $weights
     * @return array<int, float>
     */
    private function allocateAmount(float $amount, array $weights): array
    {
        $keys = array_keys($weights);
        if ($keys === []) {
            return [];
        }

        $totalCents = (int) round($amount * 100);
        $weightTotal = array_sum($weights);
        $remainingCents = $totalCents;
        $allocations = [];

        foreach ($keys as $index => $key) {
            $cents = $index === array_key_last($keys)
                ? $remainingCents
                : ($weightTotal > 0
                    ? (int) round($totalCents * $weights[$key] / $weightTotal)
                    : (int) floor($totalCents / count($keys)));
            $cents = min($remainingCents, max(0, $cents));
            $allocations[$key] = $cents / 100;
            $remainingCents -= $cents;
        }

        return $allocations;
    }
}
