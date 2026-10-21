<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class OrderController extends Controller
{
    public function checkout(Request $request): View
    {
        $cart = $request->session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('menu.index')->with('error', 'Your cart is empty.');
        }

        $items = MenuItem::query()->whereIn('id', array_keys($cart))->where('is_available', true)->get()->keyBy('id');
        abort_if($items->count() !== count($cart), 409, 'A cart item is no longer available. Please review your cart.');

        $total = $items->sum(fn (MenuItem $item) => (float) $item->price * (int) $cart[$item->id]);

        return view('customer.checkout', compact('items', 'cart', 'total'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['dine_in', 'take_out'])],
            'table_number' => ['nullable', 'string', 'max:20', 'required_if:type,dine_in', 'regex:/^[A-Za-z0-9 -]+$/'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = $request->session()->get('cart', []);
        abort_if(!is_array($cart) || $cart === [], 422, 'Your cart is empty.');

        $order = DB::transaction(function () use ($cart, $validated, $request): Order {
            $items = MenuItem::query()
                ->whereIn('id', array_keys($cart))
                ->where('is_available', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count($cart)) {
                throw new RuntimeException('One or more items are no longer available. Please refresh your cart.');
            }

            $total = 0.0;
            foreach ($cart as $id => $quantity) {
                $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
                if ($quantity === false || $quantity < 1 || $quantity > 99 || !$items->has((int) $id)) {
                    throw new RuntimeException('Your cart contains invalid item data.');
                }
                $total += (float) $items[(int) $id]->price * $quantity;
            }

            $order = Order::create([
                'reference_number' => $this->referenceNumber(),
                'type' => $validated['type'],
                'table_number' => $validated['type'] === 'dine_in' ? $validated['table_number'] : null,
                'special_instructions' => $validated['special_instructions'] ?? null,
                'total_amount' => round($total, 2),
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ]);

            foreach ($cart as $id => $quantity) {
                $item = $items[(int) $id];
                $order->items()->create([
                    'menu_item_id' => $item->id,
                    'item_name' => $item->name,
                    'quantity' => (int) $quantity,
                    'price' => $item->price,
                ]);
            }

            return $order;
        }, 3);

        $request->session()->forget('cart');

        return redirect()->route('orders.thank-you', $order->reference_number);
    }

    public function thankYou(string $reference): View
    {
        $order = Order::query()
            ->notHeld()
            ->with('items')
            ->where('reference_number', $reference)
            ->firstOrFail();

        return view('customer.thank-you', compact('order'));
    }

    public function board(): View
    {
        $orders = Order::query()
            ->notHeld()
            ->with('items')
            ->whereIn('status', ['pending', 'preparing'])
            ->latest()
            ->paginate(30);

        return view('staff.orders', compact('orders'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['preparing', 'ready', 'cancelled'])],
        ]);

        try {
            app(OrderService::class)->transition($order, $validated['status'], $request->user());
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Order status updated.');
    }

    private function referenceNumber(): string
    {
        do {
            $reference = 'PSR-' . now()->format('Y') . '-' . strtoupper(Str::random(4));
        } while (Order::query()->where('reference_number', $reference)->exists());

        return $reference;
    }
}
