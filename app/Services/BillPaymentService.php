<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BillPaymentService
{
    /** @param array<string, mixed> $data */
    public function split(TableSession $tableSession, array $data, User $actor): Collection
    {
        return DB::transaction(function () use ($tableSession, $data, $actor): Collection {
            $session = TableSession::query()->lockForUpdate()->findOrFail($tableSession->id);
            abort_if($session->closed_at !== null, 409, 'This table session is already closed.');
            abort_if($session->bills()->where('status', 'paid')->exists(), 409, 'Bills cannot be changed after a payment has been recorded.');
            if ($session->status === 'open') {
                $session->update(['status' => 'bill_requested', 'bill_requested_at' => now()]);
            }

            $items = OrderItem::query()
                ->whereHas('order', fn ($query) => $query->where('table_session_id', $session->id)->where('status', '!=', 'cancelled')->whereNull('held_at'))
                ->whereNull('cancelled_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            abort_if($items->isEmpty(), 409, 'There are no active items to split.');

            $mode = $data['split_method'];
            $groups = collect();
            if ($mode === 'seat') {
                $groups = $items->groupBy(fn (OrderItem $item): string => $item->seat_number === null ? 'shared' : 'seat:'.$item->seat_number)
                    ->mapWithKeys(fn (Collection $lines, string $key): array => [
                        $key => ['name' => $key === 'shared' ? 'Shared' : 'Seat '.substr($key, 5), 'items' => $lines],
                    ]);
            } elseif ($mode === 'item') {
                $count = (int) $data['bill_count'];
                $assignments = collect($data['assignments'])->keyBy('order_item_id');
                abort_unless($assignments->count() === $items->count()
                    && $items->every(fn (OrderItem $item): bool => $assignments->has($item->id)), 422, 'Assign every active item to exactly one bill.');
                for ($number = 1; $number <= $count; $number++) {
                    $groups->put((string) $number, ['name' => 'Bill '.$number, 'items' => $items->filter(
                        fn (OrderItem $item): bool => (int) $assignments->get($item->id)['bill_number'] === $number,
                    )]);
                }
                $groups = $groups->filter(fn (array $group): bool => $group['items']->isNotEmpty());
            } else {
                $count = (int) $data['bill_count'];
                $groups = collect(range(1, $count))->mapWithKeys(fn (int $number): array => [(string) $number => [
                    'name' => 'Bill '.$number,
                    'items' => $items,
                ]]);
            }
            abort_if($groups->isEmpty(), 422, 'The split did not create any bills.');

            $session->bills()->where('status', 'open')->each(function (Bill $bill): void {
                $bill->items()->delete();
                $bill->delete();
            });

            $billNumber = 0;
            foreach ($groups as $key => $group) {
                $billNumber++;
                $bill = $session->bills()->create([
                    'reference_number' => 'BILL-'.Str::upper(Str::random(12)),
                    'bill_number' => $billNumber,
                    'display_name' => $group['name'],
                    'split_method' => $mode,
                    'status' => 'open',
                    'subtotal' => 0,
                    'total' => 0,
                    'created_by' => $actor->id,
                ]);

                if ($mode === 'even') {
                    foreach ($group['items'] as $item) {
                        $unitCount = (int) $item->quantity;
                        $unitPriceCents = (int) round((float) $item->price * 100);
                        $baseCents = (int) floor(($unitPriceCents * $unitCount) / $count);
                        $extraCents = ($unitPriceCents * $unitCount) % $count;
                        $allocatedCents = $baseCents + ((int) $key <= $extraCents ? 1 : 0);
                        $allocatedQty = intdiv($unitCount, $count) + ((int) $key <= ($unitCount % $count) ? 1 : 0);
                        if ($allocatedCents > 0 || $allocatedQty > 0) {
                            $bill->items()->create([
                                'order_item_id' => $item->id,
                                'quantity' => $allocatedQty,
                                'amount' => $allocatedCents / 100,
                            ]);
                        }
                    }
                } else {
                    foreach ($group['items'] as $item) {
                        $bill->items()->create([
                            'order_item_id' => $item->id,
                            'quantity' => (int) $item->quantity,
                            'amount' => round((float) $item->price * (int) $item->quantity, 2),
                        ]);
                    }
                }

                $subtotal = round((float) $bill->items()->sum('amount'), 2);
                abort_if($subtotal <= 0, 422, 'Each bill must contain an item with a positive amount.');
                $bill->update(['subtotal' => $subtotal, 'total' => $subtotal]);
            }

            $sessionTotal = round((float) $session->bills()->sum('subtotal'), 2);
            $itemTotal = round((float) $items->sum(fn (OrderItem $item): float => (float) $item->price * (int) $item->quantity), 2);
            abort_unless($sessionTotal === $itemTotal, 422, 'Split bills must add up exactly to the table total.');

            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'Table session bills split',
                'module' => 'billing',
                'subject_type' => TableSession::class,
                'subject_id' => $session->id,
                'metadata' => ['split_method' => $mode, 'bill_count' => $billNumber, 'total' => $sessionTotal],
            ]);

            return $session->bills()->with(['items.orderItem'])->orderBy('bill_number')->get();
        }, 3);
    }

    /** @param array<string, mixed> $data @return array{payment:Payment,closed:bool} */
    public function pay(Bill $bill, array $data, User $cashier): array
    {
        return DB::transaction(function () use ($bill, $data, $cashier): array {
            User::query()->lockForUpdate()->findOrFail($cashier->id);
            $existing = Payment::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless((int) $existing->bill_id === (int) $bill->id
                    && (int) $existing->cashier_id === (int) $cashier->id, 409,
                    'This payment key was already used for another payment.');
                return ['payment' => $existing, 'closed' => $bill->tableSession->fresh()->closed_at !== null];
            }

            $sessionId = Bill::query()->whereKey($bill->id)->value('table_session_id');
            abort_if($sessionId === null, 404, 'This bill no longer exists.');
            $session = TableSession::query()->lockForUpdate()->findOrFail($sessionId);
            $lockedBill = Bill::query()->lockForUpdate()->findOrFail($bill->id);
            $existing = Payment::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless((int) $existing->bill_id === (int) $lockedBill->id
                    && (int) $existing->cashier_id === (int) $cashier->id, 409,
                    'This payment key was already used for another payment.');
                return ['payment' => $existing, 'closed' => $session->closed_at !== null];
            }
            abort_if($lockedBill->status !== 'open' || $session->closed_at !== null, 409, 'This bill is already paid or closed.');
            $lines = BillItem::query()->where('bill_id', $lockedBill->id)->with('orderItem.order')
                ->orderBy('id')->lockForUpdate()->get();
            abort_if($lines->isEmpty(), 409, 'This bill has no items.');

            $subtotal = round((float) $lines->sum('amount'), 2);
            $discountType = $data['discount_type'] ?? null;
            if ($discountType === 'CUSTOM') {
                abort_unless($cashier->hasRole('manager') || $cashier->hasRole('admin'), 403,
                    'Only a manager or administrator may apply a custom discount.');
            }
            $discountRate = null;
            $discount = 0.0;
            $vatExemption = 0.0;
            $vat = round($subtotal * 12 / 112, 2);
            $total = $subtotal;
            if (in_array($discountType, ['SC', 'PWD'], true)) {
                $net = round($subtotal / 1.12, 2);
                $vatExemption = round($subtotal - $net, 2);
                $discountRate = 20.0;
                $discount = round($net * 0.20, 2);
                $vat = 0.0;
                $total = round($net - $discount, 2);
            } elseif ($discountType === 'CUSTOM') {
                $discountRate = (float) $data['discount_rate'];
                $discount = round($subtotal * $discountRate / 100, 2);
                $total = round($subtotal - $discount, 2);
                $vat = round($total * 12 / 112, 2);
            }
            abort_if($total <= 0, 422, 'The discount must leave a total greater than zero.');
            $cashTendered = $data['payment_method'] === 'cash' ? round((float) $data['amount_tendered'], 2) : null;
            if ($data['payment_method'] === 'cash' && $cashTendered < $total) {
                throw ValidationException::withMessages(['amount_tendered' => 'Cash tendered must be at least the bill total.']);
            }

            $hasIdDiscount = in_array($discountType, ['SC', 'PWD'], true);
            $payment = Payment::query()->create([
                'idempotency_key' => $data['idempotency_key'],
                'payment_reference' => 'PAY-'.Str::upper(Str::random(12)),
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'amount' => $total,
                'amount_tendered' => $cashTendered,
                'change_given' => $cashTendered === null ? null : max(0, round($cashTendered - $total, 2)),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'vat_exemption' => $vatExemption,
                'vat' => $vat,
                'total' => $total,
                'discount_type' => $discountType,
                'discount_rate' => $discountRate,
                'discount_cardholder_name' => $hasIdDiscount ? trim($data['discount_cardholder_name']) : null,
                'discount_id_number' => $hasIdDiscount ? trim($data['discount_id_number']) : null,
                'discount_tin' => $hasIdDiscount ? ($data['discount_tin'] ?? null) : null,
                'cashier_id' => $cashier->id,
                'table_session_id' => $session->id,
                'bill_id' => $lockedBill->id,
                'paid_at' => now(),
            ]);

            $lockedBill->update([
                'status' => 'paid', 'subtotal' => $subtotal, 'discount' => $discount,
                'vat_exemption' => $vatExemption, 'vat' => $vat, 'total' => $total, 'paid_at' => now(),
            ]);
            $weights = [];
            foreach ($lines as $line) {
                $orderId = (int) $line->orderItem->order_id;
                $weights[$orderId] = ($weights[$orderId] ?? 0.0) + (float) $line->amount;
            }
            $allocations = $this->allocate($total, $weights);
            foreach ($allocations as $orderId => $amount) {
                $payment->orders()->attach($orderId, ['allocated_amount' => $amount]);
            }

            $allPaid = ! $session->bills()->where('status', '!=', 'paid')->exists();
            if ($allPaid) {
                $paidAt = now();
                $method = $data['payment_method'];
                $session->update([
                    'status' => 'closed', 'closed_at' => $paidAt, 'closed_by' => $cashier->id,
                    'paid_at' => $paidAt, 'paid_by' => $cashier->id,
                    'paid_payment_method' => $method, 'active_table_number' => null,
                    'subtotal' => $session->bills()->sum('subtotal'),
                    'discount' => $session->bills()->sum('discount'),
                    'vat_exemption' => $session->bills()->sum('vat_exemption'),
                    'vat' => $session->bills()->sum('vat'),
                    'total' => $session->bills()->sum('total'),
                ]);
                Order::query()->where('table_session_id', $session->id)->where('status', '!=', 'cancelled')->update([
                    'payment_status' => 'paid', 'payment_method' => $method,
                    'paid_at' => $paidAt, 'cashier_id' => $cashier->id,
                ]);
            }

            ActivityLog::query()->create([
                'user_id' => $cashier->id,
                'action' => 'Bill payment recorded',
                'module' => 'billing',
                'subject_type' => Bill::class,
                'subject_id' => $lockedBill->id,
                'metadata' => [
                    'table_session_id' => $session->id, 'bill_reference' => $lockedBill->reference_number,
                    'payment_reference' => $payment->payment_reference, 'payment_method' => $payment->payment_method,
                    'subtotal' => $subtotal, 'discount' => $discount, 'vat' => $vat, 'total' => $total,
                    'closed' => $allPaid,
                ],
            ]);

            return ['payment' => $payment, 'closed' => $allPaid];
        }, 3);
    }

    /** @param array<int, float> $weights @return array<int, float> */
    private function allocate(float $amount, array $weights): array
    {
        $keys = array_keys($weights);
        $cents = (int) round($amount * 100);
        $weightTotal = array_sum($weights);
        $remaining = $cents;
        $result = [];
        foreach ($keys as $index => $key) {
            $part = $index === array_key_last($keys)
                ? $remaining
                : (int) round($cents * $weights[$key] / max(0.01, $weightTotal));
            $part = min($remaining, max(0, $part));
            $result[$key] = $part / 100;
            $remaining -= $part;
        }
        return $result;
    }
}
