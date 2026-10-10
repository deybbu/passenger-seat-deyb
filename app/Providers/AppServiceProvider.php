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
    }
}
