<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Services\OrderPaymentService;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Staff builds an order for a walk-in customer in one screen. */
class WalkInOrderController extends Controller
{
    public function create(Request $request): View
    {
        $items = MenuItem::query()
            ->with(['category:id,name'])
            ->withStockAvailability()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('menu_category_id')
                ->orWhereHas('category', fn ($category) => $category->where('is_active', true)))
            ->orderBy('name')
            ->get();
        $menuItems = $items->map(function (MenuItem $item): array {
            $ingredients = $item->ingredients;
            $availableStock = $ingredients->isEmpty()
                ? null
                : (int) $ingredients->map(
                    fn (Ingredient $ingredient): int => (int) floor(
                        max(0, (float) $ingredient->current_stock - (float) ($ingredient->reserved_quantity ?? 0))
                        / (float) $ingredient->pivot->quantity
                        + 0.0005
                    )
                )->min();
            $available = $item->isOrderable();

            return [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category?->name ?? 'Uncategorized',
                'price' => (float) $item->price,
                'stock' => $item->is_available ? $availableStock : 0,
                'available' => $available,
                'photo' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
            ];
        });
        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->merge($menuItems->where('category', 'Uncategorized')->isNotEmpty() ? ['Uncategorized'] : [])
            ->unique()
            ->values();
        if (! $request->user()->hasRole('cashier')) {
            $staffCategories = MenuCategory::query()
                ->where('is_active', true)
                ->whereHas('items', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->with(['items' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->withStockAvailability()])
                ->get();

            return view('staff.orders.create', [
                'categories' => $staffCategories,
                'uncategorizedItems' => MenuItem::query()
                    ->whereNull('menu_category_id')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->withStockAvailability()
                    ->get(),
            ]);
        }

        return view('pos.register', [
            'menuItems' => $menuItems,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, OrderService $orders, OrderPaymentService $payments): RedirectResponse
    {
        $tableRules = ['required_if:type,dine_in', 'nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]+$/'];
        if (Schema::hasTable('restaurant_tables') && RestaurantTable::query()->exists()) {
            $tableRules[] = 'exists:restaurant_tables,table_number';
        }

        $rules = [
            'type' => ['required', Rule::in(['dine_in', 'take_out'])],
            'table_number' => $tableRules,
            'customer_name' => ['nullable', 'string', 'max:60'],
            'customer_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:' . CartService::MAX_QTY],
        ];
        if ($request->user()->hasRole('cashier')) {
            $rules += [
                'payment_method' => ['required', Rule::in(['cash', 'card', 'gcash'])],
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
                'amount_tendered' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'required_if:payment_method,cash'],
            ];
        }
        $data = $request->validate($rules, [
            'table_number.required_if' => 'Enter the table number for dine-in.',
        ]);

        $quantities = collect($data['items'])->map(fn ($q) => (int) $q)->filter(fn (int $q) => $q > 0);

        if ($quantities->isEmpty()) {
            return back()->withInput()->withErrors(['items' => 'Add at least one item to the order.']);
        }

        $menu = MenuItem::with('ingredients')->whereIn('id', $quantities->keys())->get()->keyBy('id');

        $lines = $quantities
            ->map(function (int $qty, $id) use ($menu) {
                $item = $menu->get($id);

                return $item === null ? null : (object) [
                    'item' => $item,
                    'qty' => $qty,
                    'line_total' => round((float) $item->price * $qty, 2),
                ];
            })
            ->filter()
            ->values();

        $data['customer_name'] = $data['customer_name'] ?? 'Walk-in';

        try {
            if (! $request->user()->hasRole('cashier')) {
                $order = $orders->place($data, $lines, $request->user());

                return redirect()->route('staff.orders.index')
                    ->with('status', "Order {$order->reference_number} created. Send the customer to the counter to pay.");
            }

            $order = DB::transaction(function () use ($data, $lines, $request, $orders, $payments) {
                $order = $orders->place($data, $lines, $request->user());
                $payments->record($order, $data, $request->user());

                return $order;
            }, 3);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.pos.receipt', $order)
            ->with('success', "Order {$order->reference_number} created and payment recorded.");
    }
}
