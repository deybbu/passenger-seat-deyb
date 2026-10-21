<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\SystemSetting;
use App\Models\TableSession;
use App\Services\OrderService;
use App\Services\TableSessionBillCalculator;
use App\Http\Resources\CustomerMenuItemResource;
use App\Http\Requests\StoreTableQrOrderRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerQrOrderController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $qrToken = trim((string) $request->query('qr', ''));
        $restaurantTable = $qrToken !== '' && Schema::hasTable('restaurant_tables')
            ? RestaurantTable::query()->where('qr_token', $qrToken)->first()
            : null;

        if ($restaurantTable === null || ! $restaurantTable->is_enabled || ! $this->qrEnabled()) {
            return view('customer.table-order', [
                'invalidTable' => true,
                'tableNumber' => null,
                'tableToken' => null,
                'items' => [],
            ]);
        }

        return redirect()->route('customer.order.show', ['tableToken' => $restaurantTable->qr_token]);
    }

    public function showTable(string $tableToken): View
    {
        $table = RestaurantTable::query()->where('qr_token', $tableToken)->first();
        if ($table === null || ! $table->is_enabled || ! $this->qrEnabled()) {
            return view('customer.table-order', [
                'invalidTable' => true,
                'tableNumber' => null,
                'tableToken' => null,
                'items' => [],
            ]);
        }

        $menuItems = $this->customerOrderableMenuItems();

        $items = CustomerMenuItemResource::collection($menuItems)->resolve();
        $menu = collect($items)
            ->groupBy('category')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn ($categoryItems, $category): array => [
                $category,
                $categoryItems->pluck('reference_item')->all(),
            ])
            ->values()
            ->all();
        $details = collect($items)
            ->mapWithKeys(fn (array $item): array => [
                $item['name'] => [$item['description'] ?? '', $item['ingredients']],
            ])
            ->all();
        $photos = collect($items)
            ->filter(fn (array $item): bool => is_string($item['photo']) && $item['photo'] !== '')
            ->mapWithKeys(fn (array $item): array => [$item['name'] => $item['photo']])
            ->all();

        return view('customer.table-order', [
            'invalidTable' => false,
            'tableNumber' => $table->table_number,
            'tableToken' => $table->qr_token,
            'orderUrl' => route('customer.order.submit', ['tableToken' => $table->qr_token], false),
            'statusUrl' => route('customer.order.status', ['tableToken' => $table->qr_token], false),
            'sessionUrl' => route('customer.order.session', ['tableToken' => $table->qr_token], false),
            'billRequestUrl' => route('customer.order.bill-request', ['tableToken' => $table->qr_token], false),
            'billCancelUrl' => route('customer.order.bill-request.cancel', ['tableToken' => $table->qr_token], false),
            'items' => $items,
            'menu' => $menu,
            'details' => $details,
            'photos' => $photos,
        ]);
    }

    public function session(string $tableToken, TableSessionBillCalculator $bill): JsonResponse
    {
        $table = RestaurantTable::query()
            ->where('qr_token', $tableToken)
            ->where('is_enabled', true)
            ->first();

        if ($table === null || ! $this->qrEnabled()) {
            return response()->json(['message' => "This QR code isn't valid."], 404);
        }

        $tableSession = TableSession::query()
            ->where('table_id', $table->id)
            ->whereIn('status', ['open', 'bill_requested'])
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();

        return response()->json([
            'session' => $tableSession === null ? null : $bill->refresh($tableSession),
            'available_item_ids' => $this->customerOrderableMenuItems()->modelKeys(),
        ]);
    }

    public function status(Request $request, string $tableToken): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
        ]);
        $table = RestaurantTable::query()
            ->where('qr_token', $tableToken)
            ->where('is_enabled', true)
            ->first();

        if ($table === null || ! $this->qrEnabled()) {
            return response()->json(['message' => "This QR code isn't valid."], 404);
        }

        $order = Order::query()
            ->notHeld()
            ->where('table_id', $table->id)
            ->where('source', 'table_qr')
            ->where('reference_number', $validated['reference'])
            ->first();

        if ($order === null) {
            return response()->json(['message' => 'Order not found for this table.'], 404);
        }

        return response()->json([
            'reference' => $order->reference_number,
            'status' => ucfirst((string) $order->status),
        ]);
    }

    public function submit(
        StoreTableQrOrderRequest $request,
        string $tableToken,
        OrderService $orders,
        TableSessionBillCalculator $bill,
    ): JsonResponse {
        $table = RestaurantTable::query()
            ->where('qr_token', $tableToken)
            ->where('is_enabled', true)
            ->first();
        if ($table === null || ! $this->qrEnabled()) {
            return response()->json(['message' => "This QR code isn't valid."], 404);
        }

        $data = $request->validated();
        $existing = Order::query()
            ->where('submission_token', $data['idempotency_key'])
            ->first();
        if ($existing !== null) {
            if ((int) $existing->table_id !== (int) $table->id) {
                return response()->json(['message' => 'This submission key was already used. Refresh the page and try again.'], 409);
            }

            return $this->tableOrderResponse($existing, $bill);
        }

        $lines = collect($data['items'])->map(fn (array $line): object => (object) [
            'item_id' => (int) $line['menu_item_id'],
            'variant_id' => isset($line['variant_id']) ? (int) $line['variant_id'] : null,
            'modifier_option_ids' => array_map('intval', $line['modifier_option_ids'] ?? []),
            'qty' => (int) $line['quantity'],
        ]);

        try {
            $order = $orders->place([
                'type' => 'dine_in',
                'table_number' => $table->table_number,
                'table_id' => $table->id,
                'table_token' => $tableToken,
                'source' => 'table_qr',
                'submission_token' => $data['idempotency_key'],
                'customer_name' => 'Table guest',
                'customer_phone' => null,
                'special_instructions' => $data['special_instructions'] ?? null,
            ], $lines);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Order::query()
                ->where('submission_token', $data['idempotency_key'])
                ->first();
            if ($existing === null || (int) $existing->table_id !== (int) $table->id) {
                throw $exception;
            }

            return $this->tableOrderResponse($existing, $bill);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return $this->tableOrderResponse($order, $bill, 201);
    }

    public function place(Request $request, OrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'table_token' => ['required', 'string', 'size:64'],
            'submission_token' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'min:1', 'max:20'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = Order::query()
            ->where('submission_token', $validated['submission_token'])
            ->first();

        if ($existing !== null) {
            return response()->json([
                'redirect' => route('customer.qr.show', ['table' => $existing->table_number]),
                'reference' => $existing->reference_number,
            ]);
        }

        if (! $this->qrEnabled()) {
            return response()->json([
                'message' => 'Customer QR ordering is currently disabled. Please ask staff for help.',
            ], 409);
        }

        $tableSession = TableSession::query()
            ->where('public_token', $validated['table_token'])
            ->whereNull('closed_at')
            ->first();

        if ($tableSession === null) {
            return response()->json([
                'message' => 'This table session has ended. Please ask staff for help.',
            ], 409);
        }

        if (! $this->tableQrEnabled($tableSession->table_number)) {
            return response()->json([
                'message' => 'QR ordering is disabled for this table. Please ask staff for help.',
            ], 409);
        }

        $quantities = collect($validated['items']);
        $items = MenuItem::query()
            ->withStockAvailability()
            ->whereIn('id', $quantities->keys())
            ->where('is_active', true)
            ->where('is_available', true)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($items->count() !== $quantities->count() || $items->contains(fn (MenuItem $item): bool => ! $item->isOrderable())) {
            throw ValidationException::withMessages([
                'items' => 'One or more selected dishes are no longer available. Refresh the menu and try again.',
            ]);
        }

        $lines = $quantities->map(function ($quantity, $id) use ($items) {
            $item = $items->get((int) $id);

            return (object) [
                'item' => $item,
                'qty' => (int) $quantity,
                'line_total' => round((float) $item->price * (int) $quantity, 2),
            ];
        })->values();

        try {
            $order = $orders->place([
                'type' => 'dine_in',
                'table_number' => $tableSession->table_number,
                'table_session_id' => $tableSession->id,
                'submission_token' => $validated['submission_token'],
                'customer_name' => 'Table guest',
                'customer_phone' => null,
                'special_instructions' => $validated['special_instructions'] ?? null,
            ], $lines);
        } catch (UniqueConstraintViolationException $exception) {
            $order = Order::query()
                ->where('submission_token', $validated['submission_token'])
                ->first();

            if ($order === null) {
                throw $exception;
            }
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'redirect' => route('customer.qr.show', ['table' => $order->table_number]),
            'reference' => $order->reference_number,
        ], 201);
    }

    public function orders(TableSession $tableSession): JsonResponse
    {
        $orders = $tableSession->orders()
            ->with('items')
            ->latest()
            ->get()
            ->map(fn (Order $order): array => [
                'reference' => $order->reference_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total_amount,
                'special_instructions' => $order->special_instructions,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item): array => [
                    'name' => $item->item_name,
                    'quantity' => (int) $item->quantity,
                ])->all(),
            ]);

        return response()->json([
            'orders' => $orders,
            'bill_requested' => $tableSession->bill_requested_at !== null,
        ]);
    }

    public function requestBillForTableToken(
        Request $request,
        string $tableToken,
        TableSessionBillCalculator $bill,
    ): JsonResponse {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:cash,gcash,card'],
        ]);

        return DB::transaction(function () use ($tableToken, $validated, $bill): JsonResponse {
            $table = RestaurantTable::query()
                ->where('qr_token', $tableToken)
                ->where('is_enabled', true)
                ->lockForUpdate()
                ->first();

            if ($table === null || ! $this->qrEnabled()) {
                return response()->json(['message' => "This QR code isn't valid."], 404);
            }

            $tableSession = TableSession::query()
                ->where('table_id', $table->id)
                ->whereIn('status', ['open', 'bill_requested'])
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            if ($tableSession === null) {
                return response()->json(['message' => 'There is no open table bill to request.'], 409);
            }

            if ($tableSession->status === 'bill_requested' || $tableSession->bill_requested_at !== null) {
                $snapshot = $bill->refresh($tableSession);

                return response()->json([
                    'message' => 'The bill has already been requested. The cashier is on the way.',
                    'session' => $snapshot,
                    'total' => $snapshot['total'] ?? (float) $tableSession->total,
                    'payment_method' => $snapshot['requested_payment_method'] ?? $tableSession->requested_payment_method,
                ]);
            }

            if (! $tableSession->orders()->where('status', '!=', 'cancelled')->exists()) {
                return response()->json(['message' => 'Place an order before requesting the bill.'], 422);
            }

            $bill->refresh($tableSession);
            $tableSession->update([
                'status' => 'bill_requested',
                'requested_payment_method' => $validated['payment_method'],
                'bill_requested_at' => now(),
            ]);

            $snapshot = $bill->refresh($tableSession);
            ActivityLog::query()->create([
                'action' => 'Customer requested table bill',
                'module' => 'billing',
                'subject_type' => TableSession::class,
                'subject_id' => $tableSession->id,
                'metadata' => [
                    'table_number' => $tableSession->table_number,
                    'payment_method' => $validated['payment_method'],
                    'total' => $snapshot['total'] ?? (float) $tableSession->total,
                    'order_count' => $snapshot['order_count'] ?? 0,
                ],
            ]);

            return response()->json([
                'message' => 'Bill requested. The cashier is on the way.',
                'session' => $snapshot,
                'total' => $snapshot['total'] ?? (float) $tableSession->total,
                'payment_method' => $snapshot['requested_payment_method'] ?? $tableSession->requested_payment_method,
            ]);
        }, 3);
    }

    public function cancelBillRequest(string $tableToken, TableSessionBillCalculator $bill): JsonResponse
    {
        return DB::transaction(function () use ($tableToken, $bill): JsonResponse {
            $table = RestaurantTable::query()
                ->where('qr_token', $tableToken)
                ->where('is_enabled', true)
                ->lockForUpdate()
                ->first();

            if ($table === null || ! $this->qrEnabled()) {
                return response()->json(['message' => "This QR code isn't valid."], 404);
            }

            $tableSession = TableSession::query()
                ->where('table_id', $table->id)
                ->whereIn('status', ['open', 'bill_requested'])
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            if ($tableSession === null) {
                return response()->json(['message' => 'This table bill is no longer open.'], 409);
            }

            if ($tableSession->status === 'bill_requested') {
                $tableSession->update([
                    'status' => 'open',
                    'requested_payment_method' => null,
                    'bill_requested_at' => null,
                ]);
                ActivityLog::query()->create([
                    'action' => 'Customer cancelled table bill request',
                    'module' => 'billing',
                    'subject_type' => TableSession::class,
                    'subject_id' => $tableSession->id,
                    'metadata' => ['table_number' => $tableSession->table_number],
                ]);
            }

            return response()->json([
                'message' => 'Bill request cancelled. You may place another order.',
                'session' => $bill->refresh($tableSession),
            ]);
        }, 3);
    }

    public function requestBill(TableSession $tableSession): JsonResponse
    {
        $updated = DB::table('table_sessions')
            ->where('id', $tableSession->id)
            ->whereNull('closed_at')
            ->update(['bill_requested_at' => now(), 'updated_at' => now()]);

        if ($updated === 0) {
            return response()->json(['message' => 'This table session has ended. Please ask staff for help.'], 409);
        }

        return response()->json(['message' => 'Bill request sent. Staff will be with you shortly.']);
    }

    private function qrEnabled(): bool
    {
        if (! Schema::hasTable('system_settings')) {
            return true;
        }

        $setting = SystemSetting::query()->find('qr_enabled');
        if ($setting === null || $setting->value === null) {
            return true;
        }

        return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    private function customerOrderableMenuItems(): EloquentCollection
    {
        return MenuItem::query()
            ->with([
                'category:id,name',
                'variants',
                'modifierGroups.options',
            ])
            ->withStockAvailability()
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereNull('archived_at')
            ->where(fn ($query) => $query
                ->whereDoesntHave('category')
                ->orWhereHas('category', fn ($category) => $category->where('is_active', true)))
            ->orderBy('name')
            ->get()
            ->filter(fn (MenuItem $item): bool => $item->isOrderable()
                && ($item->variants->isEmpty() || $item->variants->contains('is_active', true))
                && $item->modifierGroups->every(
                    fn ($group): bool => ! $group->is_required
                        || $group->options->contains('is_active', true),
                )
                && ! $item->modifierGroups->contains('is_required', true))
            ->values();
    }

    private function tableQrEnabled(string $tableNumber): bool
    {
        if ($tableNumber === '' || ! Schema::hasTable('restaurant_tables')) {
            return true;
        }

        $table = RestaurantTable::query()->where('table_number', $tableNumber)->first();

        return $table?->is_enabled ?? true;
    }

    private function tableOrderResponse(
        Order $order,
        TableSessionBillCalculator $bill,
        int $statusCode = 200,
    ): JsonResponse
    {
        $order->loadMissing('tableSession');

        return response()->json([
            'reference' => $order->reference_number,
            'status' => $order->status,
            'batch_number' => $order->batch_number,
            'subtotal' => (float) $order->subtotal,
            'vat_amount' => (float) $order->vat_amount,
            'total' => (float) $order->total_amount,
            'session' => $order->tableSession === null ? null : $bill->refresh($order->tableSession),
        ], $statusCode);
    }
}
