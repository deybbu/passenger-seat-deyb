<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class POSController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->with('items')
            ->where('payment_status', 'unpaid')
            ->whereNot('status', 'cancelled')
            ->oldest()
            ->paginate(30);

        return view('pos.index', compact('orders'));
    }

    public function markPaid(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'card', 'gcash'])],
        ]);

        abort_if($order->status === 'cancelled', 422, 'Cancelled orders cannot be paid.');

        DB::transaction(function () use ($order, $validated): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_if($locked->payment_status === 'paid', 409, 'This order has already been paid.');

            $locked->update([
                'payment_status' => 'paid',
                'payment_method' => $validated['payment_method'],
                'paid_at' => now(),
            ]);
        }, 3);

        return back()->with('success', 'Payment recorded.');
    }

    public function ledger(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['pending', 'preparing', 'completed', 'cancelled'])],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $orders = Order::query()
            ->with('items')
            ->when($validated['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($validated['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['q'] ?? null, fn ($q, $search) => $q->where('reference_number', 'like', '%' . addcslashes($search, '%_') . '%'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('pos.ledger', compact('orders'));
    }

    public function receipt(Order $order): View
    {
        $order->load('items');

        return view('pos.receipt', compact('order'));
    }
}
