<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderBoardController extends Controller
{
    public function index(Request $request): View
    {
        $data = [
            'pending' => Order::with('items')->where('status', 'pending')->oldest()->get(),
            'preparing' => Order::with('items')->where('status', 'preparing')->oldest()->get(),
            'completed' => Order::with('items')->where('status', 'completed')
                ->whereDate('updated_at', today())->latest('updated_at')->limit(15)->get(),
        ];

        // The page refreshes itself by asking for just the board HTML.
        return $request->ajax()
            ? view('staff.orders._board', $data)
            : view('staff.orders.index', $data);
    }

    public function transition(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        // Cashiers can watch the board; only kitchen (and admin) can move orders.
        abort_unless($request->user()->hasRole('kitchen'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['preparing', 'completed', 'cancelled'])],
        ]);

        try {
            $orders->transition($order, $data['status'], $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "{$order->reference_number} is now {$data['status']}.");
    }
}
