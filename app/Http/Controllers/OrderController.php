<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function checkout(Request $request): View|RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('menu')->with('error', 'Your cart is empty.');
        }

        $items = MenuItem::query()->withStockAvailability()->whereIn('id', array_keys($cart))
            ->where('is_available', true)->get()->filter(fn (MenuItem $item): bool => $item->isOrderable())->keyBy('id');
        abort_if($items->count() !== count($cart), 409, 'A cart item is no longer available. Please review your cart.');

        $total = $items->sum(fn (MenuItem $item) => (float) $item->price * (int) $cart[$item->id]);

        return view('customer.checkout', compact('items', 'cart', 'total'));
    }

    public function store(Request $request, OrderService $orders): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['dine_in', 'take_out'])],
            'table_number' => ['nullable', 'string', 'max:20', 'required_if:type,dine_in', 'regex:/^[A-Za-z0-9 -]+$/'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = $request->session()->get('cart', []);
        abort_if(!is_array($cart) || $cart === [], 422, 'Your cart is empty.');

        $lines = collect($cart)->map(function ($quantity, $id): object {
            $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 1 || $quantity > 99 || ! ctype_digit((string) $id)) {
                throw ValidationException::withMessages(['items' => 'Your cart contains invalid item data.']);
            }

            return (object) ['item_id' => (int) $id, 'qty' => $quantity];
        })->values();

        $data = [
            ...$validated,
            'source' => 'website',
            'customer_name' => 'Website guest',
            'submission_token' => (string) Str::uuid(),
        ];

        try {
            $order = DB::transaction(function () use ($data, $lines, $orders): Order {
                if ($data['type'] === 'dine_in') {
                    $table = RestaurantTable::query()
                        ->where('table_number', $data['table_number'])
                        ->where('is_enabled', true)
                        ->lockForUpdate()
                        ->first();
                    if ($table === null) {
                        throw ValidationException::withMessages(['table_number' => 'Choose an active restaurant table.']);
                    }

                    $session = TableSession::query()
                        ->where('table_id', $table->id)
                        ->whereIn('status', ['open', 'bill_requested'])
                        ->whereNull('closed_at')
                        ->lockForUpdate()
                        ->latest('opened_at')
                        ->first();
                    if ($session?->status === 'bill_requested' || $session?->bill_requested_at !== null) {
                        throw new \DomainException('A bill has already been requested for this table. Cancel the request before ordering again.');
                    }
                    if ($session === null) {
                        $session = TableSession::query()->create([
                            'table_number' => $table->table_number,
                            'table_id' => $table->id,
                            'public_token' => (string) Str::random(64),
                            'active_table_number' => $table->table_number,
                            'status' => 'open',
                            'opened_at' => now(),
                        ]);
                    }
                    $data['table_id'] = $table->id;
                    $data['table_session_id'] = $session->id;
                }

                return $orders->place($data, $lines);
            }, 3);
        } catch (\DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $request->session()->forget('cart');
        $tracked = $request->session()->get('website_order_references', []);
        $tracked[$order->reference_number] = true;
        $request->session()->put('website_order_references', array_slice($tracked, -20, null, true));

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

    public function status(Request $request, string $reference): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->session()->get("website_order_references.{$reference}") === true, 404);
        $order = Order::query()->notHeld()->where('reference_number', $reference)->firstOrFail();

        return response()->json([
            'status' => (string) $order->status,
            'server_now' => now('Asia/Manila')->getTimestamp() * 1000,
        ]);
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
