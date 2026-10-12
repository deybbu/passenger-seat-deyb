<?php

namespace App\Providers;

use App\Models\Ingredient;
use App\Models\Order;
use App\Services\AdminAlertService;
use App\Support\AdminSettings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.admin', function ($view): void {
            $alerts = collect();
            $lowStockCount = 0;
            $orderBadge = 0;

            if (Schema::hasTable('ingredients')) {
                $lowStockCount = Ingredient::query()
                    ->where('manual_override', false)
                    ->whereColumn('current_stock', '<=', 'min_threshold')
                    ->count();
            }

            if (Schema::hasTable('orders')) {
                $orderBadge = Order::query()->whereIn('status', ['pending', 'preparing'])->count();
            }

            $alerts = app(AdminAlertService::class)->all(request());

            $settings = AdminSettings::all();

            $view->with([
                'adminAlerts' => $alerts,
                'adminUnreadCount' => $alerts->where('unread', true)->count(),
                'lowStockCount' => $lowStockCount,
                'orderBadge' => $orderBadge,
                'adminSettings' => $settings,
            ]);
        });

        View::composer('layouts.pos-register', function ($view): void {
            $pendingOrderCount = 0;
            $unpaidOrderCount = 0;
            $user = auth()->user();

            if ($user?->hasRole('cashier') && Schema::hasTable('orders')) {
                $pendingOrderCount = Order::query()->where('status', 'pending')->count();
                $unpaidOrderCount = Order::query()
                    ->where('payment_status', 'unpaid')
                    ->whereNot('status', 'cancelled')
                    ->get(['id', 'type', 'table_number'])
                    ->map(fn (Order $order): string => $order->type === 'dine_in' && $order->table_number
                        ? 'table:'.$order->table_number
                        : 'order:'.$order->id)
                    ->unique()
                    ->count();
            }

            $view->with(compact('pendingOrderCount', 'unpaidOrderCount'));
        });
    }
}
