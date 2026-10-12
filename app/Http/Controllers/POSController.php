<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function orders(): View
    {
        $orders = Order::query()
            ->with('items')
            ->oldest()
            ->get()
            ->groupBy('status');

        return view('pos.orders', compact('orders'));
    }

    public function markPaid(Request $request, Order $order, OrderPaymentService $payments): RedirectResponse
    {
        $validated = $request->validate([
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
        ]);

        $paidOrder = $payments->record($order, $validated, $request->user());

        return redirect()->route('staff.pos.receipt', $paidOrder)
            ->with('success', 'Payment recorded. The order status has not been changed.');
    }

    public function analytics(): View
    {
        $rangeEnd = now()->endOfDay();
        $rangeStart = $rangeEnd->copy()->startOfDay()->subDays(6);
        $orders = Order::query()
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$rangeStart, $rangeEnd])
            ->with(['items.menuItem.category'])
            ->orderBy('paid_at')
            ->get();

        $dailyRevenue = [];
        $categorySales = [];
        $itemSales = [];
        $paymentSales = [];
        $todayCategorySales = [];
        $todayItemSales = [];
        $todayPaymentSales = [];

        foreach ($orders as $order) {
            $grossSubtotal = (float) $order->items->sum(
                fn (OrderItem $item): float => (float) $item->price * (int) $item->quantity,
            );
            $allocationBase = (float) $order->subtotal > 0 ? (float) $order->subtotal : $grossSubtotal;
            $orderTotal = (float) $order->total_amount;
            $paidDate = $order->paid_at->toDateString();
            $dailyRevenue[$paidDate] = ($dailyRevenue[$paidDate] ?? 0.0) + $orderTotal;
            $isToday = $paidDate === now()->toDateString();

            $method = strtolower((string) ($order->payment_method ?? 'not_recorded'));
            $paymentSales[$method] ??= [
                'method' => match ($method) {
                    'cash' => 'Cash',
                    'card' => 'Card',
                    'gcash' => 'GCash',
                    default => 'Not recorded',
                },
                'orders' => 0,
                'sales' => 0.0,
            ];
            $paymentSales[$method]['orders']++;
            $paymentSales[$method]['sales'] += $orderTotal;
            if ($isToday) {
                $todayPaymentSales[$method] ??= [
                    'method' => $paymentSales[$method]['method'],
                    'orders' => 0,
                    'sales' => 0.0,
                ];
                $todayPaymentSales[$method]['orders']++;
                $todayPaymentSales[$method]['sales'] += $orderTotal;
            }

            foreach ($order->items as $item) {
                $quantity = (int) $item->quantity;
                $grossSales = (float) $item->price * $quantity;
                $netSales = $allocationBase > 0
                    ? $orderTotal * $grossSales / $allocationBase
                    : 0.0;
                $category = $item->menuItem?->category?->name ?? 'Uncategorized';
                $categorySales[$category] ??= ['name' => $category, 'sales' => 0.0];
                $categorySales[$category]['sales'] += $netSales;
                if ($isToday) {
                    $todayCategorySales[$category] ??= ['name' => $category, 'sales' => 0.0];
                    $todayCategorySales[$category]['sales'] += $netSales;
                }

                $name = trim($item->item_name) ?: 'Unnamed item';
                $itemKey = mb_strtolower($name).'|'.mb_strtolower($category);
                $itemSales[$itemKey] ??= [
                    'name' => $name,
                    'category' => $category,
                    'quantity' => 0,
                    'sales' => 0.0,
                ];
                $itemSales[$itemKey]['quantity'] += $quantity;
                $itemSales[$itemKey]['sales'] += $netSales;
                if ($isToday) {
                    $todayItemSales[$itemKey] ??= [
                        'name' => $name,
                        'category' => $category,
                        'quantity' => 0,
                        'sales' => 0.0,
                    ];
                    $todayItemSales[$itemKey]['quantity'] += $quantity;
                    $todayItemSales[$itemKey]['sales'] += $netSales;
                }
            }
        }

        $baseTrend = collect(range(0, 6))
            ->map(function (int $offset) use ($rangeStart, $dailyRevenue): array {
                $date = $rangeStart->copy()->addDays($offset);
                $dateKey = $date->toDateString();

                return [
                    'date' => $dateKey,
                    'label' => $date->format('D'),
                    'date_label' => $date->format('M j'),
                    'revenue' => (float) ($dailyRevenue[$dateKey] ?? 0),
                ];
            });
        $maxDailyRevenue = max((float) $baseTrend->max('revenue'), 1.0);
        $weeklyTrend = $baseTrend
            ->values()
            ->map(function (array $day, int $index) use ($maxDailyRevenue): array {
                $day['x'] = 38 + ($index * 87);
                $day['y'] = (int) round(174 - ($day['revenue'] / $maxDailyRevenue * 130));
                $day['point'] = $day['x'].','.$day['y'];

                return $day;
            });

        $categorySales = collect(array_values($categorySales))->sortByDesc('sales')->values();
        $todayCategorySales = collect(array_values($todayCategorySales))->sortByDesc('sales')->values();
        $topItems = collect(array_values($itemSales))
            ->sort(fn (array $left, array $right): int => ($right['quantity'] <=> $left['quantity'])
                ?: ($right['sales'] <=> $left['sales']))
            ->values()
            ->take(10);
        $todayTopItems = collect(array_values($todayItemSales))
            ->sort(fn (array $left, array $right): int => ($right['quantity'] <=> $left['quantity'])
                ?: ($right['sales'] <=> $left['sales']))
            ->values()
            ->take(6);
        $paymentSales = collect(array_values($paymentSales))->sortByDesc('sales')->values();
        $todayPaymentSales = collect(array_values($todayPaymentSales))->sortByDesc('sales')->values();
        $maxCategorySales = max((float) $categorySales->max('sales'), 1.0);
        $weeklyRevenue = (float) $orders->sum(fn (Order $order): float => (float) $order->total_amount);
        $completedOrderCount = $orders->count();
        $averageOrderValue = $completedOrderCount > 0 ? $weeklyRevenue / $completedOrderCount : null;
        $todayRevenue = (float) ($dailyRevenue[now()->toDateString()] ?? 0);
        $yesterdayRevenue = (float) ($dailyRevenue[now()->copy()->subDay()->toDateString()] ?? 0);
        $todayOrderCount = $orders->filter(
            fn (Order $order): bool => $order->paid_at?->isToday() ?? false,
        )->count();
        $todayAverageOrderValue = $todayOrderCount > 0 ? $todayRevenue / $todayOrderCount : null;
        $todayTopCategory = $todayCategorySales->first()['name'] ?? 'None yet';
        $todayPendingOrderCount = Order::query()->where('status', 'pending')->count();

        return view('pos.analytics', compact(
            'rangeStart',
            'rangeEnd',
            'weeklyTrend',
            'weeklyRevenue',
            'completedOrderCount',
            'averageOrderValue',
            'categorySales',
            'maxCategorySales',
            'topItems',
            'paymentSales',
            'todayRevenue',
            'yesterdayRevenue',
            'todayOrderCount',
            'todayAverageOrderValue',
            'todayTopCategory',
            'todayPendingOrderCount',
            'todayTopItems',
            'todayPaymentSales',
        ));
    }

    public function ledger(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['pending', 'preparing', 'ready', 'completed', 'cancelled'])],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $orders = Order::query()
            ->with(['items', 'creator'])
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
        $order->load(['items', 'creator']);

        return view('pos.receipt', compact('order'));
    }
}
