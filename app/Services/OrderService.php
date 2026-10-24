<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\MenuModifierOption;
use App\Models\RestaurantTable;
use App\Models\ActivityLog;
use App\Models\TableSession;
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
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly TableSessionBillCalculator $tableSessionBills,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, object{item?: \App\Models\MenuItem, item_id?: int, qty: int, variant_id?: ?int, modifier_option_ids?: array<int, int>}>  $lines
     *
     * @throws \DomainException when stock or availability fails
     */
    public function place(array $data, Collection $lines, ?User $staff = null): Order
    {
        if ($lines->isEmpty()) {
            throw new \DomainException('The order is empty.');
        }

        return DB::transaction(function () use ($data, $lines, $staff) {
            $isTableQr = ($data['source'] ?? null) === 'table_qr';
            $isCustomerOrder = $isTableQr || in_array($data['source'] ?? null, ['cashier', 'website'], true);
            $table = null;
            $tableSession = null;
            $batchNumber = null;
            if ($isTableQr) {
                $table = RestaurantTable::query()
                    ->whereKey($data['table_id'] ?? 0)
                    ->where('qr_token', $data['table_token'] ?? '')
                    ->where('is_enabled', true)
                    ->lockForUpdate()
                    ->first();

                if ($table === null) {
                    throw new \DomainException("This QR code isn't valid.");
                }

                $data['table_number'] = $table->table_number;

                $tableSession = TableSession::query()
                    ->where('table_id', $table->id)
                    ->whereIn('status', ['open', 'bill_requested'])
                    ->whereNull('closed_at')
                    ->lockForUpdate()
                    ->first();

                if ($tableSession?->status === 'bill_requested' || $tableSession?->bill_requested_at !== null) {
                    throw new \DomainException(
                        'A bill has already been requested for this table. Cancel the request before ordering again.',
                    );
                }

                if ($tableSession === null) {
                    throw new \DomainException(
                        "Table {$table->table_number} isn't open yet. Please ask staff to start a new table session.",
                    );
                }
            }

            $lockedLines = $lines->sortBy(
                fn ($line) => $line->item_id ?? $line->item->id,
            )->map(function ($line) use ($isTableQr, $isCustomerOrder) {
                $sourceItem = $line->item ?? null;
                $itemId = (int) ($line->item_id ?? $sourceItem?->id ?? 0);
                $item = MenuItem::query()
                    ->with(['category', 'ingredients'])
                    ->whereKey($itemId)
                    ->lockForUpdate()
                    ->first();

                if ($item === null || ! $item->isOrderable()
                    || ($isCustomerOrder && ($item->archived_at !== null
                        || ($item->category !== null && ! $item->category->is_active)))) {
                    throw new \DomainException('One or more selected dishes are no longer available.');
                }

                $quantity = (int) $line->qty;
                $variant = null;
                $hasVariants = $isTableQr && $item->variants()->exists();
                if (isset($line->variant_id)) {
                    $variant = MenuItemVariant::query()
                        ->whereKey($line->variant_id)
                        ->where('menu_item_id', $item->id)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();
                    if ($variant === null) {
                        throw new \DomainException('A selected size is no longer available.');
                    }
                } elseif ($hasVariants) {
                    throw new \DomainException('Choose an available size for each selected dish.');
                }

                $modifierOptionIds = array_values(array_unique(array_map(
                    'intval',
                    $line->modifier_option_ids ?? [],
                )));
                $modifierOptions = collect();
                if ($modifierOptionIds !== []) {
                    $modifierOptions = MenuModifierOption::query()
                        ->with('group')
                        ->whereIn('id', $modifierOptionIds)
                        ->where('is_active', true)
                        ->whereHas('group', fn ($group) => $group->where('menu_item_id', $item->id))
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($modifierOptions->count() !== count($modifierOptionIds)) {
                        throw new \DomainException('One or more selected options are no longer available.');
                    }
                }

                if ($isTableQr) {
                    $selectedGroupIds = $modifierOptions->pluck('menu_modifier_group_id')->unique();
                    $missingRequiredGroup = $item->modifierGroups()
                        ->where('is_required', true)
                        ->whereNotIn('id', $selectedGroupIds)
                        ->exists();
                    if ($missingRequiredGroup) {
                        throw new \DomainException('Choose an option from each required group.');
                    }
                }

                $unitPrice = round(
                    (float) ($variant?->price ?? $item->price)
                        + (float) $modifierOptions->sum('price_delta'),
                    2,
                );
                $modifierSnapshot = $modifierOptions->map(fn (MenuModifierOption $option): array => [
                    'id' => (int) $option->id,
                    'group' => $option->group->name,
                    'name' => $option->name,
                    'price_delta' => (float) $option->price_delta,
                ])->values()->all();

                return (object) [
                    'item' => $item,
                    'variant' => $variant,
                    'modifier_snapshot' => $modifierSnapshot,
                    'qty' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($unitPrice * $quantity, 2),
                ];
            })->values();

            if ($lockedLines->isEmpty()) {
                throw new \DomainException('The order is empty.');
            }

            if (! $isTableQr && isset($data['table_session_id'])) {
                $tableSession = TableSession::query()
                    ->whereKey($data['table_session_id'])
                    ->whereNull('closed_at')
                    ->lockForUpdate()
                    ->first();

                if ($tableSession === null || $tableSession->table_number !== (string) $data['table_number']) {
                    throw new \DomainException('This table session is no longer open. Please ask staff for help.');
                }
                if ($tableSession->status === 'bill_requested' || $tableSession->bill_requested_at !== null) {
                    throw new \DomainException(
                        'A bill has already been requested for this table. Cancel the request before ordering again.',
                    );
                }
            }

            if ($tableSession !== null) {
                $batchNumber = (int) $tableSession->orders()->max('batch_number') + 1;
            }

            $subtotal = round((float) $lockedLines->sum('line_total'), 2);
            $vatAmount = $isTableQr ? round($subtotal * 12 / 112, 2) : 0.0;
            $total = $subtotal;
            $orderAttributes = [
                'reference_number' => $this->nextReference(),
                'type' => $data['type'],
                'table_number' => $data['type'] === 'dine_in' ? $data['table_number'] : null,
                'customer_name' => $data['customer_name'] ?? 'Table guest',
                'customer_phone' => $data['customer_phone'] ?? null,
                'special_instructions' => $data['special_instructions'] ?? null,
                'total_amount' => $total,
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ];

            if (($isTableQr || isset($data['table_id'])) && Schema::hasColumn('orders', 'table_id')) {
                $orderAttributes['table_id'] = $isTableQr ? $table->id : $data['table_id'];
            }
            if (Schema::hasColumn('orders', 'source')) {
                $orderAttributes['source'] = $data['source'] ?? ($isTableQr ? 'table_qr' : 'staff');
            }
            if ($tableSession !== null && Schema::hasColumn('orders', 'table_session_id')) {
                $orderAttributes['table_session_id'] = $tableSession->id;
            }
            if ($batchNumber !== null && Schema::hasColumn('orders', 'batch_number')) {
                $orderAttributes['batch_number'] = $batchNumber;
            }
            if (isset($data['submission_token']) && Schema::hasColumn('orders', 'submission_token')) {
                $orderAttributes['submission_token'] = $data['submission_token'];
            }
            if ($staff !== null && Schema::hasColumn('orders', 'cashier_id')) {
                $orderAttributes['cashier_id'] = $staff->id;
            }
            if (Schema::hasColumn('orders', 'order_type')) {
                $orderAttributes['order_type'] = $data['type'];
            }
            if (Schema::hasColumn('orders', 'subtotal')) {
                $orderAttributes['subtotal'] = $subtotal;
            }
            if (Schema::hasColumn('orders', 'total')) {
                $orderAttributes['total'] = $total;
            }
            if (Schema::hasColumn('orders', 'vat_amount')) {
                $orderAttributes['vat_amount'] = $vatAmount;
            }

            $order = Order::create($orderAttributes);
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'pending',
                'changed_by' => $staff?->id,
                'note' => ($data['source'] ?? null) === 'cashier' && $batchNumber !== null
                    ? "Cashier order placed as batch {$batchNumber}"
                    : 'Order placed',
            ]);

            foreach ($lockedLines as $line) {
                $itemAttributes = [
                    'menu_item_id' => $line->item->id,
                    'name' => $line->item->name,
                    'quantity' => $line->qty,
                    'price' => $line->unit_price,
                ];

                if (Schema::hasColumn('order_items', 'menu_item_variant_id')) {
                    $itemAttributes['menu_item_variant_id'] = $line->variant?->id;
                }
                if (Schema::hasColumn('order_items', 'variant_name_snapshot')) {
                    $itemAttributes['variant_name_snapshot'] = $line->variant?->name;
                }
                if (Schema::hasColumn('order_items', 'category_name_snapshot')) {
                    $itemAttributes['category_name_snapshot'] = $line->item->category?->name;
                }
                if ($line->modifier_snapshot !== [] && Schema::hasColumn('order_items', 'modifier_snapshot')) {
                    $itemAttributes['modifier_snapshot'] = $line->modifier_snapshot;
                }
                if (Schema::hasColumn('order_items', 'line_total')) {
                    $itemAttributes['line_total'] = round((float) $line->line_total, 2);
                }

                $order->items()->create($itemAttributes);
            }

            $this->inventory->reserveForOrder($order, $lockedLines);
            $this->inventory->consumeReservationsForOrder($order, $staff);

            if ($isTableQr) {
                ActivityLog::query()->create([
                    'user_id' => $staff?->id,
                    'action' => 'Customer table QR order placed',
                    'module' => 'orders',
                    'subject_type' => Order::class,
                    'subject_id' => $order->id,
                    'metadata' => [
                        'source' => 'table QR',
                        'table_number' => $table->table_number,
                        'reference_number' => $order->reference_number,
                        'batch_number' => $batchNumber,
                        'table_session_id' => $tableSession?->id,
                    ],
                ]);
            }

            if (($data['source'] ?? null) === 'cashier') {
                ActivityLog::query()->create([
                    'user_id' => $staff?->id,
                    'action' => ($data['handling'] ?? null) === 'pay_now'
                        ? 'Cashier pay-now order placed'
                        : 'Cashier pay-later order placed',
                    'module' => 'orders',
                    'subject_type' => Order::class,
                    'subject_id' => $order->id,
                    'metadata' => [
                        'source' => 'cashier',
                        'table_number' => $order->table_number,
                        'reference_number' => $order->reference_number,
                        'batch_number' => $batchNumber,
                        'table_session_id' => $tableSession?->id,
                        'customer_name' => $order->customer_name,
                    ],
                ]);
            }

            if ($tableSession !== null) {
                $this->tableSessionBills->refresh($tableSession);
            }

            return $order;
        });
    }

    /** @throws \DomainException */
    public function transition(Order $order, string $to, User $actor): Order
    {
        return DB::transaction(function () use ($order, $to, $actor) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if ($locked->held_at !== null) {
                throw new \DomainException('Held orders cannot change kitchen status.');
            }

            if (! $locked->canTransitionTo($to)) {
                throw new \DomainException("Order {$locked->reference_number} cannot go from {$locked->status} to {$to}.");
            }

            if ($to === 'cancelled') {
                if ($locked->isPaid()) {
                    throw new \DomainException('This order is already paid. Handle the refund first.');
                }

                $this->inventory->releaseOrRestoreForCancelledOrder(
                    $locked,
                    $actor,
                    restoreConsumed: $locked->status === 'pending',
                );
            }

            if ($to === 'preparing') {
                $this->inventory->consumeReservationsForOrder($locked, $actor);
            }

            $from = $locked->status;
            $locked->update(['status' => $to]);
            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor->id,
            ]);

            if ($to === 'cancelled') {
                ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'action' => $from === 'pending'
                        ? 'Order cancelled; stock restored'
                        : 'Order cancelled; stock recorded as waste',
                    'module' => 'inventory',
                    'subject_type' => Order::class,
                    'subject_id' => $locked->id,
                    'metadata' => [
                        'reference_number' => $locked->reference_number,
                        'from_status' => $from,
                    ],
                ]);
            }

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
