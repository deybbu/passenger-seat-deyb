<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\TableSessionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TableSessionPaymentService
{
    /**
     * @param array<string, mixed> $data
     */
    public function record(TableSession $tableSession, array $data, User $cashier): TableSessionPayment
    {
        return DB::transaction(function () use ($tableSession, $data, $cashier): TableSessionPayment {
            $table = RestaurantTable::query()
                ->lockForUpdate()
                ->findOrFail($tableSession->table_id);
            $session = TableSession::query()->lockForUpdate()->findOrFail($tableSession->id);

            abort_if(
                $session->status !== 'bill_requested' || $session->closed_at !== null,
                409,
                'This table bill is not ready for payment.',
            );

            $orders = $session->orders()
                ->where('status', '!=', 'cancelled')
                ->whereNull('held_at')
                ->orderBy('batch_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $itemsByOrder = OrderItem::query()
                ->whereIn('order_id', $orders->modelKeys())
                ->whereNull('cancelled_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('order_id');
            $billableOrders = $orders->filter(
                fn (Order $order): bool => $itemsByOrder->get($order->id, collect())->isNotEmpty(),
            )->values();
            abort_if($billableOrders->isEmpty(), 409, 'There are no active items on this table bill.');

            $subtotals = $billableOrders->mapWithKeys(fn (Order $order): array => [
                $order->id => round((float) $itemsByOrder->get($order->id)->sum(
                    fn (OrderItem $item): float => (float) $item->price * (int) $item->quantity,
                ), 2),
            ])->all();
            $subtotal = round(array_sum($subtotals), 2);
            $totals = $this->calculateTotals($subtotal, $data);
            $cashTendered = $data['payment_method'] === 'cash'
                ? round((float) $data['amount_tendered'], 2)
                : $totals['total'];

            if ($data['payment_method'] === 'cash' && $cashTendered < $totals['total']) {
                throw ValidationException::withMessages([
                    'amount_tendered' => 'Cash tendered must be at least the table bill total.',
                ]);
            }

            $discounts = $this->allocateAmount($totals['discount'], $subtotals);
            $vatWeights = [];
            $vatExemptionWeights = [];
            foreach ($subtotals as $orderId => $orderSubtotal) {
                $vatWeights[$orderId] = max(0, $orderSubtotal - $discounts[$orderId]);
                $vatExemptionWeights[$orderId] = $orderSubtotal;
            }
            $vatByOrder = $this->allocateAmount($totals['vat'], $vatWeights);
            $vatExemptionByOrder = $this->allocateAmount($totals['vat_exemption'], $vatExemptionWeights);
            $totalsByOrder = [];
            foreach ($subtotals as $orderId => $orderSubtotal) {
                $totalsByOrder[$orderId] = round(
                    $orderSubtotal - $discounts[$orderId] - $vatExemptionByOrder[$orderId],
                    2,
                );
            }
            $tenderedByOrder = $this->allocateAmount(
                $cashTendered,
                $data['payment_method'] === 'cash' ? $totalsByOrder : $subtotals,
            );
            $paymentReference = 'SESSION-'.Str::upper(Str::random(12));
            $paidAt = now();
            $hasIdDiscount = in_array($totals['discount_type'], ['SC', 'PWD'], true);

            $payment = $session->payments()->create([
                'type' => 'payment',
                'payment_method' => $data['payment_method'],
                'payment_reference' => $paymentReference,
                'amount' => $totals['total'],
                'amount_tendered' => $cashTendered,
                'change_given' => max(0, round($cashTendered - $totals['total'], 2)),
                'subtotal' => $subtotal,
                'discount' => $totals['discount'],
                'vat_exemption' => $totals['vat_exemption'],
                'vat' => $totals['vat'],
                'total' => $totals['total'],
                'discount_type' => $totals['discount_type'],
                'discount_rate' => $totals['discount_rate'],
                'performed_by' => $cashier->id,
            ]);

            foreach ($billableOrders as $order) {
                $updates = [
                    'subtotal' => $subtotals[$order->id],
                    'discount_type' => $totals['discount_type'],
                    'discount_rate' => $totals['discount_rate'],
                    'discount_amount' => $discounts[$order->id],
                    'discount_cardholder_name' => $hasIdDiscount ? trim($data['discount_cardholder_name']) : null,
                    'discount_id_number' => $hasIdDiscount ? trim($data['discount_id_number']) : null,
                    'discount_tin' => $hasIdDiscount && isset($data['discount_tin'])
                        ? trim($data['discount_tin'])
                        : null,
                    'vat_exemption' => $vatExemptionByOrder[$order->id],
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
                $order->update($updates);
            }

            $session->update([
                'status' => 'closed',
                'subtotal' => $subtotal,
                'discount' => $totals['discount'],
                'vat_exemption' => $totals['vat_exemption'],
                'vat' => $totals['vat'],
                'total' => $totals['total'],
                'paid_at' => $paidAt,
                'paid_payment_method' => $data['payment_method'],
                'paid_by' => $cashier->id,
                'closed_at' => $paidAt,
                'closed_by' => $cashier->id,
                'active_table_number' => null,
            ]);
            $table->update(['is_enabled' => false]);

            ActivityLog::query()->create([
                'user_id' => $cashier->id,
                'action' => 'Table session payment recorded',
                'module' => 'billing',
                'subject_type' => TableSession::class,
                'subject_id' => $session->id,
                'metadata' => [
                    'table_number' => $table->table_number,
                    'payment_reference' => $paymentReference,
                    'payment_method' => $data['payment_method'],
                    'order_count' => $billableOrders->count(),
                    'subtotal' => $subtotal,
                    'discount' => $totals['discount'],
                    'vat_exemption' => $totals['vat_exemption'],
                    'vat' => $totals['vat'],
                    'total' => $totals['total'],
                ],
            ]);

            return $payment;
        }, 3);
    }

    public function reopen(TableSession $tableSession, string $reason, User $manager): TableSessionPayment|Payment
    {
        abort_unless(
            in_array($manager->assignedRole?->key, ['restaurant_manager', 'super_admin'], true),
            403,
            'Only a Restaurant Manager or Super Admin can reopen a paid table bill.',
        );

        return DB::transaction(function () use ($tableSession, $reason, $manager): TableSessionPayment|Payment {
            $table = RestaurantTable::query()
                ->lockForUpdate()
                ->findOrFail($tableSession->table_id);
            $session = TableSession::query()->lockForUpdate()->findOrFail($tableSession->id);

            abort_if(
                $session->status !== 'closed' || $session->closed_at === null,
                409,
                'Only a paid and closed table bill can be reopened.',
            );

            $anotherActiveSession = TableSession::query()
                ->where('table_id', $table->id)
                ->where('id', '!=', $session->id)
                ->whereIn('status', ['open', 'bill_requested'])
                ->whereNull('closed_at')
                ->exists();
            abort_if(
                $anotherActiveSession,
                409,
                'A newer table session is already active. Close it before reopening this bill.',
            );

            $recordedPayments = Payment::query()
                ->where('table_session_id', $session->id)
                ->whereNull('reversed_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($recordedPayments->isNotEmpty()) {
                $reversedAt = now();
                foreach ($recordedPayments as $recordedPayment) {
                    $recordedPayment->update([
                        'reversed_at' => $reversedAt,
                        'reversed_by' => $manager->id,
                        'reversal_reason' => trim($reason),
                    ]);
                }

                $bills = Bill::query()->where('table_session_id', $session->id)
                    ->orderBy('bill_number')->lockForUpdate()->get();
                foreach ($bills as $bill) {
                    $subtotal = round((float) BillItem::query()->where('bill_id', $bill->id)->sum('amount'), 2);
                    $bill->update([
                        'status' => 'open',
                        'subtotal' => $subtotal,
                        'discount' => 0,
                        'vat_exemption' => 0,
                        'vat' => round($subtotal * 12 / 112, 2),
                        'total' => $subtotal,
                        'paid_at' => null,
                    ]);
                }

                $orders = $session->orders()->where('payment_status', 'paid')->orderBy('id')->lockForUpdate()->get();
                foreach ($orders as $order) {
                    $subtotal = round((float) OrderItem::query()->where('order_id', $order->id)
                        ->whereNull('cancelled_at')->sum(DB::raw('price * quantity')), 2);
                    $updates = [
                        'subtotal' => $subtotal,
                        'discount_type' => null,
                        'discount_rate' => null,
                        'discount_amount' => 0,
                        'discount_cardholder_name' => null,
                        'discount_id_number' => null,
                        'discount_tin' => null,
                        'vat_exemption' => 0,
                        'vat_amount' => round($subtotal * 12 / 112, 2),
                        'total' => $subtotal,
                        'total_amount' => $subtotal,
                        'payment_status' => 'unpaid',
                        'payment_method' => null,
                        'amount_tendered' => null,
                        'paid_at' => null,
                        'cashier_id' => null,
                    ];
                    if (Schema::hasColumn('orders', 'payment_reference')) {
                        $updates['payment_reference'] = null;
                    }
                    $order->update($updates);
                }

                $subtotal = round((float) $bills->sum('subtotal'), 2);
                $session->update([
                    'status' => 'bill_requested',
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'vat_exemption' => 0,
                    'vat' => round($subtotal * 12 / 112, 2),
                    'total' => $subtotal,
                    'paid_at' => null,
                    'paid_payment_method' => null,
                    'paid_by' => null,
                    'closed_at' => null,
                    'closed_by' => null,
                    'active_table_number' => $table->table_number,
                    'bill_requested_at' => now(),
                ]);

                ActivityLog::query()->create([
                    'user_id' => $manager->id,
                    'action' => 'Paid table session reopened',
                    'module' => 'billing',
                    'subject_type' => TableSession::class,
                    'subject_id' => $session->id,
                    'metadata' => [
                        'table_number' => $table->table_number,
                        'original_payment_references' => $recordedPayments->pluck('payment_reference')->all(),
                        'amount' => (float) $recordedPayments->sum('total'),
                        'reason' => trim($reason),
                    ],
                ]);

                return $recordedPayments->last()->fresh();
            }

            $payment = $session->payments()
                ->where('type', 'payment')
                ->whereDoesntHave('reversal')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            abort_if($payment === null, 409, 'There is no unreversed payment to reopen.');

            $reversal = $session->payments()->create([
                'type' => 'reversal',
                'payment_method' => $payment->payment_method,
                'payment_reference' => 'REV-'.Str::upper(Str::random(12)),
                'amount' => $payment->amount,
                'subtotal' => $payment->subtotal,
                'discount' => $payment->discount,
                'vat_exemption' => $payment->vat_exemption,
                'vat' => $payment->vat,
                'total' => $payment->total,
                'discount_type' => $payment->discount_type,
                'discount_rate' => $payment->discount_rate,
                'performed_by' => $manager->id,
                'reversal_of_id' => $payment->id,
                'notes' => trim($reason),
            ]);

            $orders = $session->orders()
                ->where('payment_status', 'paid')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $itemsByOrder = OrderItem::query()
                ->whereIn('order_id', $orders->modelKeys())
                ->whereNull('cancelled_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('order_id');
            foreach ($orders as $order) {
                $subtotal = round((float) $itemsByOrder->get($order->id, collect())->sum(
                    fn (OrderItem $item): float => (float) $item->price * (int) $item->quantity,
                ), 2);
                $updates = [
                    'subtotal' => $subtotal,
                    'discount_type' => null,
                    'discount_rate' => null,
                    'discount_amount' => 0,
                    'discount_cardholder_name' => null,
                    'discount_id_number' => null,
                    'discount_tin' => null,
                    'vat_exemption' => 0,
                    'vat_amount' => round($subtotal * 12 / 112, 2),
                    'total' => $subtotal,
                    'total_amount' => $subtotal,
                    'payment_status' => 'unpaid',
                    'payment_method' => null,
                    'amount_tendered' => null,
                    'paid_at' => null,
                    'cashier_id' => null,
                ];
                if (Schema::hasColumn('orders', 'payment_reference')) {
                    $updates['payment_reference'] = null;
                }
                $order->update($updates);
            }

            $subtotal = round((float) $orders->sum(
                fn (Order $order): float => (float) $itemsByOrder->get($order->id, collect())->sum(
                    fn (OrderItem $item): float => (float) $item->price * (int) $item->quantity,
                ),
            ), 2);
            $session->update([
                'status' => 'bill_requested',
                'subtotal' => $subtotal,
                'discount' => 0,
                'vat_exemption' => 0,
                'vat' => round($subtotal * 12 / 112, 2),
                'total' => $subtotal,
                'paid_at' => null,
                'paid_payment_method' => null,
                'paid_by' => null,
                'closed_at' => null,
                'closed_by' => null,
                'active_table_number' => $table->table_number,
                'bill_requested_at' => now(),
            ]);

            ActivityLog::query()->create([
                'user_id' => $manager->id,
                'action' => 'Paid table session reopened',
                'module' => 'billing',
                'subject_type' => TableSession::class,
                'subject_id' => $session->id,
                'metadata' => [
                    'table_number' => $table->table_number,
                    'original_payment_reference' => $payment->payment_reference,
                    'reversal_reference' => $reversal->payment_reference,
                    'amount' => (float) $payment->amount,
                    'reason' => trim($reason),
                ],
            ]);

            return $reversal;
        }, 3);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{discount_type:?string, discount_rate:?float, discount:float, vat_exemption:float, vat:float, total:float}
     */
    private function calculateTotals(float $subtotal, array $data): array
    {
        $discountType = $data['discount_type'] ?? null;
        $discountRate = null;
        $discount = 0.0;
        $vatExemption = 0.0;
        $vat = round($subtotal * 12 / 112, 2);
        $total = $subtotal;

        if (in_array($discountType, ['SC', 'PWD'], true)) {
            $discountRate = 20.0;
            $vatExclusive = round($subtotal / 1.12, 2);
            $vatExemption = round($subtotal - $vatExclusive, 2);
            $discount = round($vatExclusive * 0.20, 2);
            $vat = 0.0;
            $total = round($vatExclusive - $discount, 2);
        } elseif ($discountType === 'CUSTOM') {
            $discountRate = (float) $data['discount_rate'];
            $discount = round($subtotal * $discountRate / 100, 2);
            $total = round($subtotal - $discount, 2);
            $vat = round($total * 12 / 112, 2);
        }

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'discount_rate' => 'The discount must leave a total greater than zero.',
            ]);
        }

        return [
            'discount_type' => $discountType,
            'discount_rate' => $discountRate,
            'discount' => $discount,
            'vat_exemption' => $vatExemption,
            'vat' => $vat,
            'total' => $total,
        ];
    }

    /**
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
