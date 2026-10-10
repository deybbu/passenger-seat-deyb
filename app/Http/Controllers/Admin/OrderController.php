<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('status')->toString() ?: 'all';
        $search = trim($request->string('q')->toString());

        $query = Order::with('items')->latest();

        if ($filter !== 'all' && in_array($filter, ['pending', 'preparing', 'ready', 'completed', 'cancelled'], true)) {
            $query->where('status', $filter);
        } else {
            $filter = 'all';
            $query->where('status', '!=', 'cancelled');
        }

        if ($search !== '') {
            $safe = addcslashes($search, '%_');
            $query->where('reference_number', 'like', '%'.$safe.'%');
        }

        $orders = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => Order::where('status', '!=', 'cancelled')->count(),
            'pending' => Order::where('status', 'pending')->count(),
            'preparing' => Order::where('status', 'preparing')->count(),
            'ready' => Order::where('status', 'ready')->count(),
            'completed' => Order::where('status', 'completed')->count(),
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
}
