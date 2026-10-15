<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $range = in_array((int) $request->query('range'), [7, 30], true) ? (int) $request->query('range') : 7;
        $totalCol = Schema::hasColumn('orders', 'total_amount') ? 'total_amount' : 'total';

        $todaySales = Order::whereDate('created_at', $today)
            ->whereIn('status', ['completed', 'ready', 'preparing', 'pending'])
            ->sum($totalCol);

        $totalOrders = Order::whereDate('created_at', $today)->count();

        $preparingOrders = Order::where('status', 'preparing')->count();

        $lowStockItems = Ingredient::query()
            ->withUsableStock()
            ->get()
            ->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock())
            ->sortBy(fn (Ingredient $ingredient): float => $ingredient->usableStock())
            ->values();
        $lowStockCount = $lowStockItems->count();
        $lowStockItems = $lowStockItems->take(5);

        $recentOrders = Order::with('items')
            ->latest()
            ->limit(4)
            ->get();

        $salesChart = [];
        for ($i = $range - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $salesChart[] = [
                'label' => $range === 7 ? $date->format('D') : $date->format('j'),
                'title' => $date->format('M j'),
                'total' => (float) Order::whereDate('created_at', $date)
                    ->whereIn('status', ['completed', 'ready', 'preparing', 'pending'])
                    ->sum($totalCol),
            ];
        }

        $maxSale = max(array_column($salesChart, 'total')) ?: 1;

        return view('admin.dashboard', compact(
            'todaySales',
            'totalOrders',
            'preparingOrders',
            'lowStockCount',
            'lowStockItems',
            'recentOrders',
            'salesChart',
            'maxSale',
            'range',
        ));
    }

    public function inventory(): View
    {
        $ingredients = Ingredient::query()->orderBy('name')->get();

        return view('admin.inventory', compact('ingredients'));
    }

    public function pos(): View
    {
        $query = Order::with('items');

        if (Schema::hasColumn('orders', 'payment_status')) {
            $query->where('payment_status', 'paid');
        } else {
            $query->where('status', 'completed');
        }

        $paidCol = Schema::hasColumn('orders', 'paid_at') ? 'paid_at' : 'updated_at';
        $orders = $query->latest($paidCol)->paginate(20);

        return view('admin.pos', compact('orders'));
    }

    public function menuManagement(): View
    {
        $items = MenuItem::with('category')->orderBy('name')->get();
        $categories = \App\Models\MenuCategory::query()->orderBy('name')->get();

        return view('admin.menu-management', compact('items', 'categories'));
    }

    public function barcode(): View
    {
        $ingredients = Ingredient::query()->orderBy('name')->get();

        return view('admin.barcode', compact('ingredients'));
    }
}
