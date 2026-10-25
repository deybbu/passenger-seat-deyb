<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\CartService;
use App\Services\OrderPaymentService;
use App\Services\OrderService;
use App\Support\MenuImageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function index(Request $request): View
    {
        $items = MenuItem::query()
            ->with(['category:id,name'])
            ->withStockAvailability()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('menu_category_id')
                ->orWhereHas('category', fn (Builder $category) => $category->where('is_active', true)))
            ->orderBy('name')
            ->get();

        $menuItems = $items->map(function (MenuItem $item): array {
            $ingredients = $item->ingredients;
            $availableStock = $ingredients->isEmpty()
                ? null
                : (int) $ingredients->map(
                    fn ($ingredient): int => (int) floor(
                        max(0, (float) $ingredient->current_stock - (float) ($ingredient->reserved_quantity ?? 0))
                        / (float) $ingredient->pivot->quantity
                        + 0.0005
                    )
                )->min();

            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category?->name ?? 'Uncategorized',
                'price' => (float) $item->price,
                'stock' => $item->is_available ? $availableStock : 0,
                'available' => $item->isOrderable(),
                'photo' => MenuImageUrl::forPath($item->image_path),
            ];
        });

        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->merge($menuItems->where('category', 'Uncategorized')->isNotEmpty() ? ['Uncategorized'] : [])
            ->unique()
            ->values();

        $heldOrders = Order::query()
            ->whereNotNull('held_at')
            ->where('cashier_id', $request->user()->id)
            ->with('items')
            ->oldest('held_at')
            ->get()
            ->map(fn (Order $order): array => $this->heldOrderData($order));

        $tables = RestaurantTable::query()->orderBy('table_number')->get(['table_number', 'name']);

        return view('admin.register', compact('menuItems', 'categories', 'heldOrders', 'tables'));
    }

    public function store(
        Request $request,
        OrderService $orders,
        OrderPaymentService $payments,
    ): RedirectResponse {
        $data = $this->validateRegisterData($request, false);
        $data['customer_name'] = trim((string) ($data['customer_name'] ?? '')) ?: 'Walk-in';

        try {
            $order = DB::transaction(function () use ($data, $request, $orders, $payments): Order {
                if (! empty($data['held_order_id'])) {
                    $heldOrder = Order::query()
                        ->whereKey($data['held_order_id'])
                        ->whereNotNull('held_at')
                        ->where('cashier_id', $request->user()->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $heldOrder->items()->delete();
                    $heldOrder->delete();
                }

                $order = $orders->place($data, $this->menuLines($data['items']), $request->user());
                $payments->record($order, $data, $request->user());

                return $order;
            }, 3);
        } catch (\DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.pos')
            ->with('success', "Order {$order->reference_number} created and payment recorded.");
    }

    public function referencePlace(
        Request $request,
        OrderService $orders,
        OrderPaymentService $payments,
    ): JsonResponse {
        $data = $this->validateRegisterData($request, false);
        $data['customer_name'] = trim((string) ($data['customer_name'] ?? '')) ?: 'Walk-in';
        try {
            $order = DB::transaction(function () use ($data, $request, $orders, $payments): Order {
                $order = $orders->place($data, $this->menuLines($data['items']), $request->user());
                $payments->record($order, $data, $request->user());
                return $order->load('items');
            }, 3);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        return response()->json(['reference' => $order->reference_number]);
    }

    public function hold(Request $request): JsonResponse
    {
        $data = $this->validateRegisterData($request, true);
        $lines = $this->menuLines($data['items']);
        $data['customer_name'] = trim((string) ($data['customer_name'] ?? '')) ?: 'Walk-in';

        $heldOrder = DB::transaction(function () use ($data, $request, $lines): Order {
            $heldOrder = null;
            if (! empty($data['held_order_id'])) {
                $heldOrder = Order::query()
                    ->whereKey($data['held_order_id'])
                    ->whereNotNull('held_at')
                    ->where('cashier_id', $request->user()->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $heldOrder->items()->delete();
            }

            $subtotal = round((float) $lines->sum('line_total'), 2);
            $discountType = $data['discount_type'] ?? null;
            $orderAttributes = [
                'type' => $data['type'],
                'order_type' => $data['type'],
                'table_number' => $data['type'] === 'dine_in' ? $data['table_number'] : null,
                'customer_name' => $data['customer_name'],
                'special_instructions' => $data['special_instructions'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'total_amount' => $subtotal,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $data['payment_method'],
                'cashier_id' => $request->user()->id,
                'discount_type' => $discountType,
                'discount_rate' => $discountType === 'CUSTOM'
                    ? (float) ($data['discount_rate'] ?? 0)
                    : (in_array($discountType, ['SC', 'PWD'], true) ? 20 : null),
                'discount_amount' => 0,
                'discount_cardholder_name' => in_array($discountType, ['SC', 'PWD'], true)
                    ? trim((string) ($data['discount_cardholder_name'] ?? ''))
                    : null,
                'discount_id_number' => in_array($discountType, ['SC', 'PWD'], true)
                    ? trim((string) ($data['discount_id_number'] ?? ''))
                    : null,
                'discount_tin' => in_array($discountType, ['SC', 'PWD'], true)
                    ? trim((string) ($data['discount_tin'] ?? ''))
                    : null,
                'held_at' => now(),
            ];

            if ($heldOrder === null) {
                $heldOrder = Order::query()->create([
                    ...$orderAttributes,
                    'reference_number' => 'HOLD-'.Str::upper(Str::random(20)),
                ]);
            } else {
                $heldOrder->update($orderAttributes);
            }

            foreach ($lines as $line) {
                $heldOrder->items()->create([
                    'menu_item_id' => $line->item->id,
                    'name' => $line->item->name,
                    'quantity' => $line->qty,
                    'price' => $line->item->price,
                    'line_total' => $line->line_total,
                ]);
            }

            return $heldOrder->load('items');
        }, 3);

        return response()->json(['order' => $this->heldOrderData($heldOrder)]);
    }

    public function resume(Request $request, Order $order): JsonResponse
    {
        $heldOrder = Order::query()
            ->whereKey($order->id)
            ->whereNotNull('held_at')
            ->where('cashier_id', $request->user()->id)
            ->with('items')
            ->firstOrFail();

        return response()->json($this->heldOrderData($heldOrder));
    }

    public function discard(Request $request, Order $order): JsonResponse
    {
        DB::transaction(function () use ($request, $order): void {
            $heldOrder = Order::query()
                ->whereKey($order->id)
                ->whereNotNull('held_at')
                ->where('cashier_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();
            $heldOrder->items()->delete();
            $heldOrder->delete();
        }, 3);

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRegisterData(Request $request, bool $holding): array
    {
        $tableRules = [
            ...($holding ? ['nullable'] : ['required_if:type,dine_in', 'nullable']),
            'string',
            'max:10',
            'regex:/^[A-Za-z0-9\-]+$/',
        ];
        if (Schema::hasTable('restaurant_tables') && RestaurantTable::query()->exists()) {
            $tableRules[] = 'exists:restaurant_tables,table_number';
        }

        $rules = [
            'type' => ['required', Rule::in(['dine_in', 'take_out'])],
            'table_number' => $tableRules,
            'customer_name' => ['nullable', 'string', 'max:60'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QTY],
            'held_order_id' => ['nullable', 'integer'],
            'discount_type' => ['nullable', Rule::in(['SC', 'PWD', 'CUSTOM'])],
            'discount_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                ...($holding ? [] : ['required_if:discount_type,CUSTOM']),
            ],
            'discount_cardholder_name' => [
                'nullable',
                'string',
                'max:120',
                ...($holding ? [] : ['required_if:discount_type,SC', 'required_if:discount_type,PWD']),
            ],
            'discount_id_number' => [
                'nullable',
                'string',
                'max:80',
                ...($holding ? [] : ['required_if:discount_type,SC', 'required_if:discount_type,PWD']),
            ],
            'discount_tin' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'gcash'])],
        ];

        if (! $holding) {
            $rules['amount_tendered'] = [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999',
                'required_if:payment_method,cash',
            ];
        }

        $data = $request->validate($rules, [
            'table_number.required_if' => 'Enter the table number for dine-in.',
        ]);
        $quantities = collect($data['items'])
            ->map(fn ($quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0);

        if ($quantities->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add at least one item to the order.']);
        }
        $data['items'] = $quantities->all();

        return $data;
    }

    /**
     * @param array<int|string, int> $quantities
     * @return Collection<int, object{item: MenuItem, qty: int, line_total: float}>
     */
    private function menuLines(array $quantities): Collection
    {
        $menuItems = MenuItem::query()
            ->with('ingredients')
            ->whereIn('id', array_keys($quantities))
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('menu_category_id')
                ->orWhereHas('category', fn (Builder $category) => $category->where('is_active', true)))
            ->get()
            ->keyBy('id');

        if ($menuItems->count() !== count($quantities)) {
            throw ValidationException::withMessages([
                'items' => 'One or more selected menu items are no longer available.',
            ]);
        }

        return collect($quantities)
            ->map(function (int $quantity, int|string $id) use ($menuItems): object {
                $item = $menuItems->get((int) $id);

                return (object) [
                    'item' => $item,
                    'qty' => $quantity,
                    'line_total' => round((float) $item->price * $quantity, 2),
                ];
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function heldOrderData(Order $order): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference_number,
            'items' => $order->items->mapWithKeys(
                fn ($item): array => [(string) $item->menu_item_id => (int) $item->quantity],
            )->all(),
            'type' => $order->type,
            'table' => $order->table_number ?? '',
            'customer' => $order->customer_name ?? '',
            'discount_type' => $order->discount_type ?? '',
            'discount_rate' => (float) ($order->discount_rate ?? 0),
            'discount_cardholder_name' => $order->discount_cardholder_name ?? '',
            'discount_id_number' => $order->discount_id_number ?? '',
            'discount_tin' => $order->discount_tin ?? '',
            'payment_method' => $order->payment_method ?? 'cash',
        ];
    }
}
