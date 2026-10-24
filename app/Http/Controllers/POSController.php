<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\User;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\TableSessionPayment;
use App\Http\Resources\PosMenuItemResource;
use App\Http\Resources\PosRestaurantTableResource;
use App\Http\Resources\PosTableSessionResource;
use App\Services\OrderPaymentService;
use App\Services\TableSessionBillCalculator;
use App\Services\TableSessionPaymentService;
use App\Services\OrderService;
use App\Services\BillPaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class POSController extends Controller
{
    public function index(): View
    {
        return view('pos.index');
    }

    public function data(Request $request): JsonResponse
    {
        $menuQuery = MenuItem::query()
            ->with(['category:id,name'])
            ->withStockAvailability()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('menu_category_id')
                ->orWhereHas('category', fn (Builder $category) => $category->where('is_active', true)))
            ->orderBy('name');
        if (Schema::hasColumn('menu_items', 'archived_at')) {
            $menuQuery->whereNull('archived_at');
        }
        $menu = $menuQuery->get();

        $tables = RestaurantTable::query()
            ->get()
            ->sortBy('table_number', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $sessionLoads = [
                'restaurantTable',
                'orders' => fn ($orders) => $orders
                    ->whereNull('held_at')
                    ->where('status', '!=', 'cancelled')
                    ->with(['items', 'creator']),
            ];
        if (Schema::hasTable('bills') && Schema::hasTable('bill_items') && Schema::hasTable('payments')) {
            $sessionLoads['bills'] = fn ($bills) => $bills
                ->with(['items.orderItem', 'payment'])
                ->orderBy('bill_number');
        }

        $sessions = TableSession::query()
            ->whereIn('status', ['open', 'bill_requested'])
            ->whereNull('closed_at')
            ->where(function (Builder $query): void {
                $query->whereHas('restaurantTable', fn (Builder $table) => $table->where('is_enabled', true))
                    ->orWhereHas('orders', fn (Builder $orders) => $orders->where('status', '!=', 'cancelled')->whereNull('held_at'))
                    ->orWhere('status', 'bill_requested');
            })
            ->with($sessionLoads)
            ->get()
            ->sortBy('table_number', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'cashier_id' => (int) $request->user()->id,
            'menu' => $menu->map(fn (MenuItem $item): array => (new PosMenuItemResource($item))->resolve($request))->values(),
            'menu_ids' => $menu->pluck('id')->values(),
            'tables' => $tables->map(fn (RestaurantTable $table): array => (new PosRestaurantTableResource($table))->resolve($request))->values(),
            'sessions' => $sessions->map(fn (TableSession $session): array => (new PosTableSessionResource($session))->resolve($request))->values(),
            'takeout_orders' => Order::query()
                ->where('source', 'cashier')
                ->where('type', 'take_out')
                ->where('payment_status', 'unpaid')
                ->where('status', '!=', 'cancelled')
                ->whereNull('held_at')
                ->with('items')
                ->oldest()
                ->get()
                ->map(fn (Order $order): array => [
                    'id' => (int) $order->id,
                    'reference' => (string) $order->reference_number,
                    'name' => (string) ($order->customer_name ?: 'Take-out'),
                    'status' => (string) $order->status,
                    'total' => (float) $order->items->sum(fn ($item) => (float) $item->price * (int) $item->quantity),
                    'items' => $order->items->map(fn ($item): array => [
                        'name' => (string) $item->item_name,
                        'quantity' => (int) $item->quantity,
                        'total' => round((float) $item->price * (int) $item->quantity, 2),
                    ])->values(),
                ])->values(),
        ]);
    }

    public function placeRegisterOrder(
        Request $request,
        OrderService $orders,
        OrderPaymentService $payments,
    ): JsonResponse
    {
        $handling = $request->input('handling', 'pay_now');
        $tableRules = ['required_if:type,dine_in', 'nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9-]+$/'];
        if (Schema::hasTable('restaurant_tables') && RestaurantTable::query()->exists()) {
            $tableRules[] = 'exists:restaurant_tables,table_number';
        }
        $data = $request->validate([
            'type' => ['required', Rule::in(['dine_in', 'take_out'])],
            'handling' => ['sometimes', Rule::in(['pay_now', 'pay_later'])],
            'table_number' => $tableRules,
            'customer_name' => ['nullable', 'string', 'max:60'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_id' => ['required', 'integer', 'distinct', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:' . \App\Services\CartService::MAX_QTY],
            ...($handling === 'pay_now' ? $this->paymentRules() : []),
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $data['handling'] = $handling;
        $data['source'] = 'cashier';

        if ($data['handling'] === 'pay_later') {
            if ($data['type'] === 'take_out') {
                $request->validate(['customer_name' => ['required', 'string', 'max:60']]);
            }
            return $this->placeRegisterOrderPayLater($data, $request, $orders);
        }

        if (($data['discount_type'] ?? null) === 'CUSTOM') {
            abort_unless($request->user()->hasRole('manager') || $request->user()->hasRole('admin'), 403,
                'Only a manager or administrator may apply a custom discount.');
        }

        if (Schema::hasTable('payments')) {
            $existing = Payment::query()->with('orders')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing?->orders->isNotEmpty()) {
                abort_unless((int) $existing->cashier_id === (int) $request->user()->id
                    && $existing->bill_id === null && $existing->table_session_id === null,
                    409, 'This payment key was already used for another payment.');
                $order = $existing->orders->first();

                $order->load('items');

                return response()->json([
                    'id' => $order->id, 'reference' => $order->reference_number,
                    'status' => $order->status, 'type' => $order->type,
                    'table_number' => $order->table_number, 'total' => (float) $existing->total,
                    'items' => $order->items->map(fn (OrderItem $item): array => [
                        'name' => $item->item_name, 'quantity' => (int) $item->quantity,
                        'price' => (float) $item->price, 'total' => round((float) $item->price * (int) $item->quantity, 2),
                    ])->values(),
                    'payment' => ['reference' => $existing->payment_reference, 'external_reference' => $existing->reference_number,
                        'method' => match ($existing->payment_method) {
                            'gcash' => 'GCash', 'card' => 'Card', default => 'Cash',
                        }, 'amount' => (float) $existing->total,
                        'tendered' => $existing->amount_tendered === null ? null : (float) $existing->amount_tendered,
                        'change' => $existing->change_given === null ? null : (float) $existing->change_given,
                        'subtotal' => (float) $existing->subtotal, 'discount' => (float) $existing->discount,
                        'vat' => (float) $existing->vat],
                ]);
            }
        }

        $quantities = collect($data['items'])->groupBy('item_id')->map(
            fn ($rows) => (int) $rows->sum('quantity'),
        );
        $items = MenuItem::query()->with('ingredients')->whereIn('id', $quantities->keys())->get()->keyBy('id');
        $lines = $quantities->map(fn (int $quantity, int|string $id) => (object) [
            'item' => $items->get($id),
            'item_id' => (int) $id,
            'qty' => $quantity,
        ])->values();

        $data['customer_name'] = trim((string) ($data['customer_name'] ?? '')) ?: 'Walk-in';
        try {
            $order = DB::transaction(function () use ($data, $lines, $request, $orders, $payments): Order {
                User::query()->lockForUpdate()->findOrFail($request->user()->id);
                if (Schema::hasTable('payments')) {
                    $duplicate = Payment::query()->with('orders')
                        ->where('idempotency_key', $data['idempotency_key'])->first();
                    if ($duplicate !== null) {
                        abort_unless((int) $duplicate->cashier_id === (int) $request->user()->id
                            && $duplicate->bill_id === null && $duplicate->table_session_id === null,
                            409, 'This payment key was already used for another payment.');
                        $existingOrder = $duplicate->orders->first();
                        abort_if($existingOrder === null, 409, 'The earlier payment is not linked to an order.');

                        return $existingOrder;
                    }
                }
                $order = $orders->place($data, $lines, $request->user());
                return $payments->record($order, $data, $request->user())->load('items');
            }, 3);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $recordedPayment = Schema::hasTable('payments')
            ? Payment::query()->where('idempotency_key', $data['idempotency_key'])->first()
            : null;

        return response()->json([
            'id' => $order->id,
            'reference' => $order->reference_number,
            'status' => $order->status,
            'type' => $order->type,
            'table_number' => $order->table_number,
            'total' => (float) $order->total_amount,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'name' => $item->item_name, 'quantity' => (int) $item->quantity,
                'price' => (float) $item->price, 'total' => round((float) $item->price * (int) $item->quantity, 2),
            ])->values(),
            'payment' => [
                'reference' => $recordedPayment?->payment_reference ?? $order->payment_reference,
                'external_reference' => $recordedPayment?->reference_number ?? ($data['reference_number'] ?? null),
                'method' => match ($recordedPayment?->payment_method ?? $data['payment_method']) {
                    'gcash' => 'GCash', 'card' => 'Card', default => 'Cash',
                },
                'amount' => (float) $order->total_amount,
                'tendered' => $recordedPayment !== null
                    ? ($recordedPayment->amount_tendered === null ? null : (float) $recordedPayment->amount_tendered)
                    : ($data['payment_method'] === 'cash' ? (float) $order->amount_tendered : null),
                'change' => $recordedPayment !== null
                    ? ($recordedPayment->change_given === null ? null : (float) $recordedPayment->change_given)
                    : ($data['payment_method'] === 'cash'
                        ? max(0, round((float) $order->amount_tendered - (float) $order->total_amount, 2)) : null),
                'subtotal' => (float) ($recordedPayment?->subtotal ?? $order->subtotal),
                'discount' => (float) ($recordedPayment?->discount ?? $order->discount_amount),
                'vat' => (float) ($recordedPayment?->vat ?? $order->vat_amount),
            ],
        ], 201);
    }

    /** @param array<string, mixed> $data */
    private function placeRegisterOrderPayLater(array $data, Request $request, OrderService $orders): JsonResponse
    {
        $data['customer_name'] = trim((string) ($data['customer_name'] ?? '')) ?: 'Walk-in';
        $data['source'] = 'cashier';
        $data['submission_token'] = $data['idempotency_key'];

        try {
            [$order, $created] = DB::transaction(function () use ($data, $request, $orders): array {
                User::query()->lockForUpdate()->findOrFail($request->user()->id);

                $existing = Order::query()
                    ->where('submission_token', $data['submission_token'])
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    abort_unless(
                        (int) $existing->cashier_id === (int) $request->user()->id
                            && $existing->source === 'cashier',
                        409,
                        'This order key was already used for another order.',
                    );

                    return [$existing->load('items'), false];
                }

                if ($data['type'] === 'dine_in') {
                    $table = RestaurantTable::query()
                        ->where('table_number', $data['table_number'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    $session = TableSession::query()
                        ->where(function ($query) use ($table, $data): void {
                            $query->where('table_id', $table->id)
                                ->orWhere('table_number', $data['table_number']);
                        })
                        ->whereIn('status', ['open', 'bill_requested'])
                        ->whereNull('closed_at')
                        ->lockForUpdate()
                        ->first();

                    if ($session !== null
                        && ($session->status === 'bill_requested' || $session->bill_requested_at !== null)) {
                        throw new \DomainException(
                            'A bill has already been requested for this table. Cancel the request before ordering again.',
                        );
                    }

                    if ($session === null) {
                        $session = TableSession::query()->create([
                            'table_number' => $table->table_number,
                            'table_id' => $table->id,
                            'public_token' => \Illuminate\Support\Str::random(64),
                            'active_table_number' => $table->table_number,
                            'opened_by' => $request->user()->id,
                            'status' => 'open',
                            'opened_at' => now(),
                        ]);
                    }

                    $data['table_number'] = $table->table_number;
                    $data['table_id'] = $table->id;
                    $data['table_session_id'] = $session->id;
                }

                $quantities = collect($data['items'])->groupBy('item_id')->map(
                    fn ($rows) => (int) $rows->sum('quantity'),
                );
                $items = MenuItem::query()->with('ingredients')->whereIn('id', $quantities->keys())->get()->keyBy('id');
                $lines = $quantities->map(fn (int $quantity, int|string $id) => (object) [
                    'item' => $items->get($id),
                    'item_id' => (int) $id,
                    'qty' => $quantity,
                ])->values();

                $order = $orders->place($data, $lines, $request->user())->load('items');

                return [$order, true];
            }, 3);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'id' => $order->id,
            'reference' => $order->reference_number,
            'status' => $order->status,
            'type' => $order->type,
            'table_number' => $order->table_number,
            'table_session_id' => $order->table_session_id,
            'batch_number' => $order->batch_number,
            'total' => (float) $order->total_amount,
            'handling' => 'pay_later',
            'payment' => null,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'name' => $item->item_name,
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->price,
                'total' => round((float) $item->price * (int) $item->quantity, 2),
            ])->values(),
            'created' => $created,
        ], $created ? 201 : 200);
    }

    public function billRequests(): JsonResponse
    {
        return response()->json(['requests' => $this->currentBillRequests()]);
    }

    public function orders(): View
    {
        $orders = Order::query()
            ->notHeld()
            ->with('items')
            ->oldest()
            ->get()
            ->groupBy('status');

        return view('pos.orders', compact('orders'));
    }

    public function markPaid(Request $request, Order $order, OrderPaymentService $payments): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            ...$this->paymentRules(),
            ...($request->expectsJson() ? ['idempotency_key' => ['required', 'uuid']] : []),
        ]);
        $this->assertDiscountAuthority($request, $validated);

        $paidOrder = $payments->record($order, $validated, $request->user());

        if ($request->expectsJson()) {
            $payment = Schema::hasTable('payments')
                ? Payment::query()->where('idempotency_key', $validated['idempotency_key'])->first()
                : null;
            $paidOrder->load('items');

            return response()->json([
                'id' => (int) $paidOrder->id,
                'reference' => (string) $paidOrder->reference_number,
                'type' => (string) $paidOrder->type,
                'table_number' => $paidOrder->table_number,
                'total' => (float) ($payment?->total ?? $paidOrder->total),
                'items' => $paidOrder->items->map(fn ($item): array => [
                    'name' => (string) $item->item_name,
                    'quantity' => (int) $item->quantity,
                    'total' => round((float) $item->price * (int) $item->quantity, 2),
                ])->values(),
                'payment' => [
                    'method' => ucfirst((string) $payment?->payment_method),
                    'external_reference' => (string) ($payment?->reference_number ?? ''),
                    'reference' => (string) ($payment?->payment_reference ?? $paidOrder->payment_reference ?? ''),
                    'subtotal' => (float) ($payment?->subtotal ?? 0),
                    'discount' => (float) ($payment?->discount ?? 0),
                    'vat' => (float) ($payment?->vat ?? 0),
                    'change' => $payment?->change_given === null ? null : (float) $payment->change_given,
                ],
            ]);
        }

        return redirect()->route('staff.pos.receipt', $paidOrder)
            ->with('success', 'Payment recorded. The order status has not been changed.');
    }

    public function markTablePaid(
        Request $request,
        RestaurantTable $restaurantTable,
        OrderPaymentService $payments,
    ): RedirectResponse {
        $validated = $request->validate($this->paymentRules());
        $this->assertDiscountAuthority($request, $validated);
        $paidOrders = $payments->recordTableBill($restaurantTable, $validated, $request->user());
        $paymentReference = $paidOrders->first()?->payment_reference;

        return redirect()->route('staff.pos.index')->with(
            'success',
            'Payment recorded for '.count($paidOrders).' orders at Table '.$paidOrders->first()?->table_number
                .($paymentReference ? ' (bill '.$paymentReference.').' : '.'),
        );
    }

    public function tableSessionBill(
        TableSession $tableSession,
        TableSessionBillCalculator $bill,
    ): View {
        $snapshot = $bill->refresh($tableSession);
        abort_if($snapshot === null, 404, 'This table bill is no longer active.');
        abort_unless($snapshot['status'] === 'bill_requested', 404);

        $tableSession->load([
            'restaurantTable',
            'orders' => fn ($query) => $query->with('items')->orderBy('batch_number')->orderBy('id'),
        ]);

        return view('pos.table-session-bill', compact('tableSession', 'snapshot'));
    }

    public function markTableSessionPaid(
        Request $request,
        TableSession $tableSession,
        BillPaymentService $payments,
    ): RedirectResponse {
        $validated = $request->validate($this->paymentRules());
        $this->assertDiscountAuthority($request, $validated);
        $bills = $tableSession->bills()->where('status', 'open')->orderBy('bill_number')->get();
        if ($bills->isEmpty()) {
            $bills = $payments->split($tableSession, [
                'split_method' => 'full', 'bill_count' => 1, 'assignments' => [],
            ], $request->user());
        }
        abort_if($bills->count() !== 1, 409, 'This table has split bills. Complete payment from the Billing tab.');
        $result = $payments->pay($bills->first(), $validated + ['idempotency_key' => (string) \Illuminate\Support\Str::uuid()], $request->user());
        $payment = $result['payment'];

        return redirect()->route('staff.pos.index')->with(
            'success',
            'Payment recorded for Table '.$tableSession->table_number.' (bill '.$payment->payment_reference.').',
        );
    }

    public function splitTableSessionBills(
        Request $request,
        TableSession $tableSession,
        BillPaymentService $bills,
    ): JsonResponse {
        $data = $request->validate([
            'split_method' => ['required', Rule::in(['full', 'even', 'seat', 'item'])],
            'bill_count' => ['required_if:split_method,even,item', 'nullable', 'integer', 'min:1', 'max:8'],
            'assignments' => ['required_if:split_method,item', 'array'],
            'assignments.*.order_item_id' => ['required', 'integer', 'distinct', 'exists:order_items,id'],
            'assignments.*.bill_number' => ['required', 'integer', 'min:1', 'max:8'],
        ]);
        if (in_array($data['split_method'], ['even', 'item'], true) && (int) $data['bill_count'] < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'bill_count' => 'Choose at least two bills for this split.',
            ]);
        }
        $created = $bills->split($tableSession, $data, $request->user());

        return response()->json(['bills' => $created->map(fn (Bill $bill): array => $this->billData($bill))->values()]);
    }

    public function payBill(
        Request $request,
        Bill $bill,
        BillPaymentService $payments,
    ): JsonResponse {
        $data = $request->validate([
            ...$this->paymentRules(),
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $this->assertDiscountAuthority($request, $data);

        $result = $payments->pay($bill, $data, $request->user());
        $payment = $result['payment'];

        return response()->json([
            'payment' => [
                'reference' => $payment->payment_reference,
                'external_reference' => $payment->reference_number,
                'method' => $payment->payment_method,
                'amount' => (float) $payment->total,
                'tendered' => $payment->amount_tendered === null ? null : (float) $payment->amount_tendered,
                'change' => $payment->change_given === null ? null : (float) $payment->change_given,
                'subtotal' => (float) $payment->subtotal,
                'discount' => (float) $payment->discount,
                'vat' => (float) $payment->vat,
            ],
            'bill_id' => $bill->id,
            'table_session_id' => $bill->table_session_id,
            'closed' => $result['closed'],
        ]);
    }

    public function reopenTableSession(
        Request $request,
        TableSession $tableSession,
        TableSessionPaymentService $payments,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $reversal = $payments->reopen($tableSession, $validated['reason'], $request->user());

        $message = $reversal instanceof Payment
            ? 'Table '.$tableSession->table_number.' bill reopened. Payment '.$reversal->payment_reference.' was marked reversed.'
            : 'Table '.$tableSession->table_number.' bill reopened. Reversal '.$reversal->payment_reference.' was recorded.';

        return redirect()->route('staff.pos.ledger')->with('success', $message);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function paymentRules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(['cash', 'card', 'gcash'])],
            'reference_number' => ['nullable', 'string', 'max:100', 'required_if:payment_method,card', 'required_if:payment_method,gcash'],
            'discount_type' => ['nullable', Rule::in(['SC', 'PWD', 'CUSTOM'])],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:discount_type,CUSTOM'],
            'discount_cardholder_name' => [
                'nullable',
                'string',
                'max:120',
                'required_if:discount_type,SC',
                'required_if:discount_type,PWD',
            ],
            'discount_id_number' => [
                'nullable',
                'string',
                'max:80',
                'required_if:discount_type,SC',
                'required_if:discount_type,PWD',
            ],
            'discount_tin' => ['nullable', 'string', 'max:30'],
            'amount_tendered' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'required_if:payment_method,cash'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function assertDiscountAuthority(Request $request, array $data): void
    {
        if (($data['discount_type'] ?? null) === 'CUSTOM') {
            abort_unless($request->user()->hasRole('manager') || $request->user()->hasRole('admin'), 403,
                'Only a manager or administrator may apply a custom discount.');
        }
    }

    /** @return array{id:int,name:string,amt:float,lines:string,paid:bool,pay:?array} */
    private function billData(Bill $bill): array
    {
        $bill->loadMissing(['items.orderItem', 'payment']);
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
    }

    /**
     * @return Collection<int, array{
     *     id:int,
     *     table_number:string,
     *     payment_method:?string,
     *     total:float,
     *     order_count:int,
     *     requested_at:?string,
     *     requested_for:string,
     *     bill_url:string
     * }>
     */
    private function currentBillRequests(): Collection
    {
        return TableSession::query()
            ->where('status', 'bill_requested')
            ->whereNotNull('bill_requested_at')
            ->whereNull('closed_at')
            ->withCount(['orders' => fn (Builder $orders): Builder => $orders->where('status', '!=', 'cancelled')])
            ->orderBy('bill_requested_at')
            ->get(['id', 'table_number', 'requested_payment_method', 'total', 'bill_requested_at'])
            ->map(fn (TableSession $session): array => [
                'id' => (int) $session->id,
                'table_number' => (string) $session->table_number,
                'payment_method' => $session->requested_payment_method,
                'total' => (float) $session->total,
                'order_count' => (int) $session->orders_count,
                'requested_at' => $session->bill_requested_at?->format('Y-m-d\TH:i:s.uP'),
                'requested_for' => $session->bill_requested_at?->diffForHumans() ?? 'Just now',
                'bill_url' => route('staff.pos.table-session-bill', $session),
            ])
            ->values();
    }

    public function analytics(): View
    {
        $rangeEnd = now()->endOfDay();
        $rangeStart = $rangeEnd->copy()->startOfDay()->subDays(6);
        $orderLoads = ['items' => fn ($query) => $query->whereNull('cancelled_at')->with('menuItem.category')];
        if (Schema::hasTable('payments')) {
            $orderLoads[] = 'payments';
        }
        $orders = Order::query()
            ->notHeld()
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$rangeStart, $rangeEnd])
            ->with($orderLoads)
            ->orderBy('paid_at')
            ->get();
        $payments = Schema::hasTable('payments')
            ? Payment::query()
                ->whereNull('reversed_at')
                ->whereBetween('paid_at', [$rangeStart, $rangeEnd])
                ->with('orders')
                ->orderBy('paid_at')
                ->get()
            : collect();
        $paymentAmountsByOrder = [];
        foreach ($payments as $payment) {
            foreach ($payment->orders as $paidOrder) {
                $paymentAmountsByOrder[$paidOrder->id] = ($paymentAmountsByOrder[$paidOrder->id] ?? 0.0)
                    + (float) $paidOrder->pivot->allocated_amount;
            }
        }

        $dailyRevenue = [];
        $categorySales = [];
        $itemSales = [];
        $paymentSales = [];
        $todayCategorySales = [];
        $todayItemSales = [];
        $todayPaymentSales = [];

        foreach ($orders as $order) {
            $grossSubtotal = (float) $order->items->sum(
                fn (OrderItem $item): float => (float) $item->price * (int) $item->quantity,
            );
            $allocationBase = (float) $order->subtotal > 0 ? (float) $order->subtotal : $grossSubtotal;
            $orderTotal = (float) ($paymentAmountsByOrder[$order->id] ?? $order->total_amount);
            $paidDate = $order->paid_at->toDateString();
            $dailyRevenue[$paidDate] = ($dailyRevenue[$paidDate] ?? 0.0) + $orderTotal;
            $isToday = $paidDate === now()->toDateString();

            $method = strtolower((string) ($order->payment_method ?? 'not_recorded'));
            $paymentSales[$method] ??= [
                'method' => match ($method) {
                    'cash' => 'Cash',
                    'card' => 'Card',
                    'gcash' => 'GCash',
                    default => 'Not recorded',
                },
                'orders' => 0,
                'sales' => 0.0,
            ];
            $paymentSales[$method]['orders']++;
            $paymentSales[$method]['sales'] += $orderTotal;
            if ($isToday) {
                $todayPaymentSales[$method] ??= [
                    'method' => $paymentSales[$method]['method'],
                    'orders' => 0,
                    'sales' => 0.0,
                ];
                $todayPaymentSales[$method]['orders']++;
                $todayPaymentSales[$method]['sales'] += $orderTotal;
            }

            foreach ($order->items as $item) {
                $quantity = (int) $item->quantity;
                $grossSales = (float) $item->price * $quantity;
                $netSales = $allocationBase > 0
                    ? $orderTotal * $grossSales / $allocationBase
                    : 0.0;
                $category = $item->menuItem?->category?->name
                    ?? $item->category_name_snapshot
                    ?? 'Uncategorized';
                $categorySales[$category] ??= ['name' => $category, 'sales' => 0.0];
                $categorySales[$category]['sales'] += $netSales;
                if ($isToday) {
                    $todayCategorySales[$category] ??= ['name' => $category, 'sales' => 0.0];
                    $todayCategorySales[$category]['sales'] += $netSales;
                }

                $name = trim($item->item_name) ?: 'Unnamed item';
                $itemKey = mb_strtolower($name).'|'.mb_strtolower($category);
                $itemSales[$itemKey] ??= [
                    'name' => $name,
                    'category' => $category,
                    'quantity' => 0,
                    'sales' => 0.0,
                ];
                $itemSales[$itemKey]['quantity'] += $quantity;
                $itemSales[$itemKey]['sales'] += $netSales;
                if ($isToday) {
                    $todayItemSales[$itemKey] ??= [
                        'name' => $name,
                        'category' => $category,
                        'quantity' => 0,
                        'sales' => 0.0,
                    ];
                    $todayItemSales[$itemKey]['quantity'] += $quantity;
                    $todayItemSales[$itemKey]['sales'] += $netSales;
                }
            }
        }

        if ($payments->isNotEmpty()) {
            $dailyRevenue = [];
            $paymentSales = [];
            $todayPaymentSales = [];
            $paymentOrderCounts = [];
            $todayPaymentOrderCounts = [];
            foreach ($payments as $payment) {
                $paidDate = $payment->paid_at->copy()->timezone(config('app.timezone'))->toDateString();
                $amount = (float) $payment->total;
                $dailyRevenue[$paidDate] = ($dailyRevenue[$paidDate] ?? 0.0) + $amount;
                $method = strtolower((string) $payment->payment_method);
                $label = match ($method) {
                    'cash' => 'Cash', 'card' => 'Card', 'gcash' => 'GCash', default => 'Not recorded',
                };
                $paymentSales[$method] ??= ['method' => $label, 'orders' => 0, 'sales' => 0.0];
                $paymentSales[$method]['orders']++;
                $paymentSales[$method]['sales'] += $amount;
                foreach ($payment->orders as $paidOrder) {
                    $paymentOrderCounts[$method][$paidOrder->id] = true;
                    if ($paidDate === now(config('app.timezone'))->toDateString()) {
                        $todayPaymentOrderCounts[$method][$paidOrder->id] = true;
                    }
                }
                if ($paidDate === now(config('app.timezone'))->toDateString()) {
                    $todayPaymentSales[$method] ??= ['method' => $label, 'orders' => 0, 'sales' => 0.0];
                    $todayPaymentSales[$method]['orders']++;
                    $todayPaymentSales[$method]['sales'] += $amount;
                }
            }
            foreach ($paymentOrderCounts as $method => $orderIds) {
                $paymentSales[$method]['orders'] = count($orderIds);
            }
            foreach ($todayPaymentOrderCounts as $method => $orderIds) {
                $todayPaymentSales[$method]['orders'] = count($orderIds);
            }
            foreach ($orders->filter(fn (Order $order): bool => $order->payments->isEmpty()) as $order) {
                $amount = (float) $order->total_amount;
                $paidDate = $order->paid_at->copy()->timezone(config('app.timezone'))->toDateString();
                $dailyRevenue[$paidDate] = ($dailyRevenue[$paidDate] ?? 0.0) + $amount;
                $method = strtolower((string) ($order->payment_method ?? 'not_recorded'));
                $label = match ($method) {
                    'cash' => 'Cash', 'card' => 'Card', 'gcash' => 'GCash', default => 'Not recorded',
                };
                $paymentSales[$method] ??= ['method' => $label, 'orders' => 0, 'sales' => 0.0];
                $paymentSales[$method]['orders']++;
                $paymentSales[$method]['sales'] += $amount;
                if ($paidDate === now(config('app.timezone'))->toDateString()) {
                    $todayPaymentSales[$method] ??= ['method' => $label, 'orders' => 0, 'sales' => 0.0];
                    $todayPaymentSales[$method]['orders']++;
                    $todayPaymentSales[$method]['sales'] += $amount;
                }
            }
        }

        $baseTrend = collect(range(0, 6))
            ->map(function (int $offset) use ($rangeStart, $dailyRevenue): array {
                $date = $rangeStart->copy()->addDays($offset);
                $dateKey = $date->toDateString();

                return [
                    'date' => $dateKey,
                    'label' => $date->format('D'),
                    'date_label' => $date->format('M j'),
                    'revenue' => (float) ($dailyRevenue[$dateKey] ?? 0),
                ];
            });
        $maxDailyRevenue = max((float) $baseTrend->max('revenue'), 1.0);
        $weeklyTrend = $baseTrend
            ->values()
            ->map(function (array $day, int $index) use ($maxDailyRevenue): array {
                $day['x'] = 38 + ($index * 87);
                $day['y'] = (int) round(174 - ($day['revenue'] / $maxDailyRevenue * 130));
                $day['point'] = $day['x'].','.$day['y'];

                return $day;
            });

        $categorySales = collect(array_values($categorySales))->sortByDesc('sales')->values();
        $todayCategorySales = collect(array_values($todayCategorySales))->sortByDesc('sales')->values();
        $topItems = collect(array_values($itemSales))
            ->sort(fn (array $left, array $right): int => ($right['quantity'] <=> $left['quantity'])
                ?: ($right['sales'] <=> $left['sales']))
            ->values()
            ->take(10);
        $todayTopItems = collect(array_values($todayItemSales))
            ->sort(fn (array $left, array $right): int => ($right['quantity'] <=> $left['quantity'])
                ?: ($right['sales'] <=> $left['sales']))
            ->values()
            ->take(6);
        $paymentSales = collect(array_values($paymentSales))->sortByDesc('sales')->values();
        $todayPaymentSales = collect(array_values($todayPaymentSales))->sortByDesc('sales')->values();
        $maxCategorySales = max((float) $categorySales->max('sales'), 1.0);
        $weeklyRevenue = $payments->isNotEmpty()
            ? (float) $payments->sum(fn (Payment $payment): float => (float) $payment->total)
                + (float) $orders->filter(fn (Order $order): bool => $order->payments->isEmpty())
                    ->sum(fn (Order $order): float => (float) $order->total_amount)
            : (float) $orders->sum(fn (Order $order): float => (float) $order->total_amount);
        $completedOrderCount = $orders->count();
        $averageOrderValue = $completedOrderCount > 0 ? $weeklyRevenue / $completedOrderCount : null;
        $todayRevenue = (float) ($dailyRevenue[now()->toDateString()] ?? 0);
        $yesterdayRevenue = (float) ($dailyRevenue[now()->copy()->subDay()->toDateString()] ?? 0);
        $todayOrderCount = $orders->filter(
            fn (Order $order): bool => $order->paid_at?->isToday() ?? false,
        )->count();
        $todayAverageOrderValue = $todayOrderCount > 0 ? $todayRevenue / $todayOrderCount : null;
        $todayTopCategory = $todayCategorySales->first()['name'] ?? 'None yet';
        $todayPendingOrderCount = Order::query()->notHeld()->where('status', 'pending')->count();

        return view('pos.analytics', compact(
            'rangeStart',
            'rangeEnd',
            'weeklyTrend',
            'weeklyRevenue',
            'completedOrderCount',
            'averageOrderValue',
            'categorySales',
            'maxCategorySales',
            'topItems',
            'paymentSales',
            'todayRevenue',
            'yesterdayRevenue',
            'todayOrderCount',
            'todayAverageOrderValue',
            'todayTopCategory',
            'todayPendingOrderCount',
            'todayTopItems',
            'todayPaymentSales',
        ));
    }

    public function ledger(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['pending', 'preparing', 'ready', 'served', 'cancelled'])],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $orders = Order::query()
            ->notHeld()
            ->with(['items', 'creator'])
            ->when($validated['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($validated['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['q'] ?? null, fn ($q, $search) => $q->where('reference_number', 'like', '%' . addcslashes($search, '%_') . '%'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $sessionPayments = TableSessionPayment::query()
            ->with(['tableSession', 'performedBy', 'reversal', 'reversalOf'])
            ->when($validated['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($validated['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($validated['q'] ?? null, function ($q, string $search): void {
                $term = '%'.addcslashes($search, '%_').'%';
                $q->where(function ($records) use ($term): void {
                    $records->where('payment_reference', 'like', $term)
                        ->orWhereHas('tableSession', fn ($sessions) => $sessions->where('table_number', 'like', $term));
                });
            })
            ->latest()
            ->paginate(20, ['*'], 'session_payments')
            ->withQueryString();
        $canReopenTableBills = in_array(
            $request->user()->assignedRole?->key,
            ['restaurant_manager', 'super_admin'],
            true,
        );

        return view('pos.ledger', compact('orders', 'sessionPayments', 'canReopenTableBills'));
    }

    public function receipt(Order $order): View
    {
        abort_if($order->held_at !== null, 404);
        $order->load(['items', 'creator']);

        return view('pos.receipt', compact('order'));
    }
}
