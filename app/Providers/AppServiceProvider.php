<?php

namespace App\Providers;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\TableSession;
use App\Services\AdminAlertService;
use App\Support\AdminSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('customer-table-order', function (Request $request): array {
            $tableToken = (string) $request->route('tableToken', '');

            return [
                Limit::perMinute(20)->by('customer-table-ip:'.($request->ip() ?? 'unknown')),
                Limit::perMinute(10)->by('customer-table-token:'.hash('sha256', $tableToken)),
            ];
        });

        View::composer('layouts.admin', function ($view): void {
            $alerts = collect();
            $lowStockCount = 0;
            $orderBadge = 0;
            $billRequestCount = Schema::hasTable('table_sessions') && Schema::hasColumn('table_sessions', 'status')
                ? TableSession::query()->where('status', 'bill_requested')->whereNull('closed_at')->count()
                : 0;

            if (Schema::hasTable('ingredients')) {
                $lowStockCount = Ingredient::query()
                    ->withUsableStock()
                    ->get()
                    ->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock())
                    ->count();
            }

            if (Schema::hasTable('orders')) {
                $orderBadge = Order::query()->notHeld()->whereIn('status', ['pending', 'preparing'])->count();
            }

            $alerts = app(AdminAlertService::class)->all(request());

            $settings = AdminSettings::all();

            $view->with([
                'adminAlerts' => $alerts,
                'adminUnreadCount' => $alerts->where('unread', true)->count(),
                'lowStockCount' => $lowStockCount,
                'orderBadge' => $orderBadge,
                'adminBillRequestCount' => $billRequestCount,
                'adminSettings' => $settings,
            ]);
        });

        View::composer('layouts.pos-register', function ($view): void {
            $pendingOrderCount = 0;
            $billRequestCount = Schema::hasTable('table_sessions') && Schema::hasColumn('table_sessions', 'status')
                ? TableSession::query()->where('status', 'bill_requested')->whereNull('closed_at')->count()
                : 0;
            $user = auth()->user();

            if ($user?->hasRole('cashier') && Schema::hasTable('orders')) {
                $pendingOrderCount = Order::query()->notHeld()->where('status', 'pending')->count();
            }

            $view->with(compact('pendingOrderCount', 'billRequestCount'));
        });
    }
}
