<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Staff builds an order for a walk-in customer in one screen. */
class WalkInOrderController extends Controller
{
    public function create(): View
    {
        $categories = Category::query()
            ->orderBy('sort_order')
            ->with(['items' => fn ($q) => $q->orderBy('sort_order')->with('ingredients')])
            ->get();

        return view('staff.orders.create', ['categories' => $categories]);
    }

    public function store(Request $request, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['dine_in', 'take_out'])],
            'table_number' => ['required_if:type,dine_in', 'nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]+$/'],
            'customer_name' => ['nullable', 'string', 'max:60'],
            'customer_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:' . CartService::MAX_QTY],
        ], [
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
            $order = $orders->place($data, $lines, $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.orders.index')
            ->with('status', "Order {$order->reference_number} created. Send the customer to the counter to pay.");
    }
}
