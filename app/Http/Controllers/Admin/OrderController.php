<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('status')->toString() ?: 'all';
        $search = trim($request->string('q')->toString());

        $query = Order::query()->notHeld()->with('items')->latest();

        if ($filter !== 'all') {
            if (in_array($filter, ['pending', 'preparing', 'ready', 'completed', 'cancelled'], true)) {
                $query->where('status', $filter);
            } else {
                $filter = 'all';
            }
        }

        if ($search !== '') {
            $safe = addcslashes($search, '%_');
            $query->where(function ($query) use ($safe): void {
                $term = '%'.$safe.'%';
                $query->where('reference_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term);

                if (Schema::hasColumn('order_items', 'item_name') || Schema::hasColumn('order_items', 'name')) {
                    $query->orWhereHas('items', function ($items) use ($term): void {
                        $items->where(function ($items) use ($term): void {
                            if (Schema::hasColumn('order_items', 'item_name')) {
                                $items->where('item_name', 'like', $term);
                            }
                            if (Schema::hasColumn('order_items', 'name')) {
                                $method = Schema::hasColumn('order_items', 'item_name') ? 'orWhere' : 'where';
                                $items->{$method}('name', 'like', $term);
                            }
                        });
                    });
                }
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => Order::query()->notHeld()->count(),
            'pending' => Order::query()->notHeld()->where('status', 'pending')->count(),
            'preparing' => Order::query()->notHeld()->where('status', 'preparing')->count(),
            'ready' => Order::query()->notHeld()->where('status', 'ready')->count(),
            'completed' => Order::query()->notHeld()->where('status', 'completed')->count(),
            'cancelled' => Order::query()->notHeld()->where('status', 'cancelled')->count(),
        ];

        return view('admin.orders', compact('orders', 'filter', 'counts', 'search'));
    }

    public function updateStatus(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['preparing', 'ready', 'completed', 'cancelled'])],
        ]);

        try {
            $orders->transition($order, $validated['status'], $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "{$order->reference_number} is now {$validated['status']}.");
    }

    public function referenceStatus(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['preparing', 'ready', 'completed', 'cancelled'])],
        ]);
        try {
            $orders->transition($order, $validated['status'], $request->user());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['status' => $order->fresh()->status]);
    }
}
