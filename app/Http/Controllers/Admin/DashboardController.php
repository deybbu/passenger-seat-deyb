<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\InventoryStockLot;
use App\Models\RestaurantTable;
use App\Models\EventInquiry;
use App\Models\EventPackage;
use App\Models\Faq;
use App\Models\UnansweredQuestion;
use App\Models\User;
use App\Models\Role;
use App\Models\ActivityLog;
use App\Models\AdminAlertRule;
use App\Services\AdminAlertService;
use App\Services\TableSessionStatusService;
use App\Support\AdminSettings;
use App\Support\TableQrUrl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function referenceData(
        Request $request,
        AdminAlertService $alertService,
        TableSessionStatusService $tableSessionStatuses,
    ): JsonResponse
    {
        $settings = AdminSettings::all();
        $tables = RestaurantTable::query()->orderBy('table_number')->get();
        $tableStatuses = $tableSessionStatuses->forTables($tables);

        return response()->json([
            'menu' => MenuItem::query()
                ->with(['category', 'ingredients', 'variants', 'tags', 'modifierGroups.options', 'bundles'])
                ->orderBy('id')
                ->get()
                ->map(fn (MenuItem $item): array => $this->referenceMenuItem($item))
                ->values(),
            'ingredients' => Ingredient::query()
                ->with(['stockLots', 'preparationRecipeLines'])
                ->orderBy('id')
                ->get()
                ->map(fn (Ingredient $ingredient): array => $this->referenceIngredient($ingredient))
                ->values(),
            'orders' => $this->referenceOrders(),
            'waste' => InventoryLog::query()
                ->where('type', 'waste')
                ->with('ingredient')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (InventoryLog $log): array => [
                    't' => $log->created_at?->toISOString(),
                    'n' => $log->ingredient?->name ?? 'Unknown ingredient',
                    'q' => sprintf('%s %s', (float) $log->quantity, $log->ingredient?->unit ?? ''),
                    'stage' => $log->stage ?? 'Unspecified',
                    'r' => $log->reason ?? $log->notes ?? 'Unspecified',
                    'v' => (float) ($log->cost_amount ?? 0),
                ])
                ->values(),
            'settings' => [
                'vat' => (float) ($settings['vat_percent'] ?? 12),
                'svc' => (float) ($settings['service_charge_percent'] ?? 10),
                'cur' => $settings['currency'] ?? 'PHP',
                'open' => $settings['open_time'] ?? '10:00',
                'close' => $settings['close_time'] ?? '22:00',
                'black' => $settings['blackout_dates'] ?? '',
                'qrEnabled' => (bool) ($settings['qr_enabled'] ?? true),
                'recipe_target_percent' => (float) ($settings['recipe_target_percent'] ?? 30),
            ],
            'purchase_orders' => PurchaseOrder::query()->with('items')->latest()->limit(100)->get()
                ->flatMap(fn (PurchaseOrder $order) => $order->items->map(fn ($item): array => [
                    'no' => $order->reference,
                    'sup' => $order->supplier_name,
                    'id' => $item->ingredient_id,
                    'item' => $item->id,
                    'ord' => (float) $item->ordered_quantity,
                    'rec' => (float) $item->received_quantity,
                    'closed' => $order->closed_at !== null,
                ]))->values(),
            'lots' => InventoryStockLot::query()->with('ingredient')->latest('received_at')->limit(100)->get()
                ->map(fn (InventoryStockLot $lot): array => [
                    't' => $lot->received_at?->toISOString(),
                    'n' => $lot->ingredient?->name ?? 'Unknown ingredient',
                    'lot' => $lot->batch_number,
                    'q' => (float) $lot->received_quantity,
                    'exp' => $lot->expires_at?->toDateString(),
                    'bin' => $lot->storage_zone ?? '',
                ])->values(),
            'history' => $this->referenceHistory(),
            'tables' => $tables->map(fn (RestaurantTable $table): array => [
                    'id' => $table->id,
                    'name' => $table->name,
                    'table_number' => $table->table_number,
                    'enabled' => (bool) $table->is_enabled,
                    'qr_url' => TableQrUrl::forToken($table->qr_token),
                    'qr_image_url' => route('admin.api.reference.table-qr.image', [
                        'restaurantTable' => $table->id,
                    ], false),
                    'guests' => 0,
                    'minutes' => 180,
                    ...$tableStatuses->get($table->id, []),
                ])->values(),
            'inquiries' => EventInquiry::query()->with(['internalNotes', 'package.menuItems'])
                ->latest('event_date')->get()->map(fn (EventInquiry $inquiry): array => [
                    'id' => $inquiry->id,
                    'd' => $inquiry->event_date?->toDateString(),
                    'n' => trim($inquiry->first_name.' '.$inquiry->last_name),
                    't' => $inquiry->event_type,
                    'pax' => $inquiry->guest_count,
                    'b' => (float) ($inquiry->budget ?? 0),
                    'st' => match ($inquiry->status) {
                        'approved', 'confirmed', 'booked' => 'Approved',
                        'declined' => 'Declined',
                        'under_review' => 'Under review',
                        default => 'New',
                    },
                    'pk' => $inquiry->package?->name ?? $inquiry->preferred_package ?? '',
                    'packageId' => $inquiry->event_package_id,
                    'em' => $inquiry->email,
                    'ph' => $inquiry->mobile_number,
                    'note' => $inquiry->dietary_restrictions ?: $inquiry->special_requests ?: '',
                    'notes' => $inquiry->internalNotes->map(fn ($note): string => $note->note.' (Admin)')->values(),
                ])->values(),
            'packages' => EventPackage::query()->where('is_active', true)->with('menuItems')
                ->orderBy('name')->get()->map(fn (EventPackage $package): array => [
                    'id' => $package->id,
                    'n' => $package->name,
                    'h' => (float) ($package->price_per_guest ?? 0),
                    'i' => $package->menuItems->pluck('id')->values(),
                ])->values(),
            'faqs' => Faq::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'question', 'answer'])->map(fn (Faq $faq): array => [$faq->question, $faq->answer, $faq->id])->values(),
            'unanswered' => UnansweredQuestion::query()->where('status', 'unanswered')->latest()
                ->get(['id', 'question'])->map(fn (UnansweredQuestion $question): array => ['id' => $question->id, 'text' => $question->question])->values(),
            'users' => User::query()->with('assignedRole')->orderBy('id')->get()
                ->map(fn (User $user): array => [$user->name, $user->assignedRole?->name ?? ucfirst((string) $user->role), (int) $user->is_active, $user->id])->values(),
            'roles' => Role::query()->with('permissions')->orderBy('id')->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('can_view')->values(),
                ])->values(),
            'rules' => AdminAlertRule::query()->orderBy('id')->get()
                ->map(fn (AdminAlertRule $rule): array => [$rule->trigger, $rule->action, (int) $rule->is_enabled, $rule->id])->values(),
            'alerts' => $alertService->all($request)->values(),
            'logs' => ActivityLog::query()->with('user')->latest()->limit(100)->get()
                ->map(fn (ActivityLog $log): array => [
                    $log->created_at?->toDateTimeString(),
                    $log->user?->name ?? 'System',
                    $log->action,
                    $log->module,
                ])->values(),
        ]);
    }

    private function referenceOrders()
    {
        return Order::query()
            ->notHeld()
            ->with(['items.menuItem', 'items.variant'])
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'no' => $order->reference_number ?: $order->id,
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'id' => $item->menu_item_id,
                    'q' => (int) $item->quantity,
                    'vi' => $item->menu_item_variant_id,
                    'pr' => (float) $item->price,
                ])->values(),
                'tot' => (float) $order->total_amount,
                'st' => ucfirst((string) $order->status),
                'type' => ucfirst(str_replace('_', '-', (string) $order->type)),
                'source' => $order->source === 'table_qr'
                    ? 'Table QR'
                    : ($order->table_session_id ? 'QR' : 'POS'),
                'paym' => $order->payment_method,
                'paymentStatus' => $order->payment_status,
                'revenue' => $order->isPaid() || $order->status === 'completed',
                'revenueToday' => ($order->isPaid() || $order->status === 'completed')
                    && ($order->created_at?->isToday() ?? false),
                'customer' => $order->customer_name,
                'tableNo' => $order->table_number,
            ])
            ->values();
    }

    private function referenceMenuItem(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'n' => $item->name,
            'c' => $item->category?->name ?? 'Uncategorized',
            'p' => (float) $item->price,
            't' => $item->collection_type ?? 'Regular',
            'on' => $item->is_active && $item->is_available && $item->archived_at === null,
            'img' => $item->image_path ? asset('storage/'.$item->image_path) : '',
            'desc' => $item->description ?? '',
            'tags' => $item->tags->pluck('name')->values(),
            'v' => $item->variants->map(fn ($variant): array => [
                'n' => $variant->name,
                'p' => (float) $variant->price,
                'f' => (float) ($variant->portion_factor ?? 1),
                'stock' => (float) ($variant->stock_quantity ?? 0),
                'min' => (float) ($variant->min_stock_quantity ?? 0),
            ])->values(),
            'mods' => $item->modifierGroups->map(fn ($group): array => [
                'g' => $group->name,
                'req' => $group->is_required,
                'o' => $group->options->map(fn ($option): array => [
                    'n' => $option->name,
                    'd' => (float) $option->price_delta,
                ])->values(),
            ])->values(),
            'bun' => $item->bundles->map(fn ($bundle): array => [
                'id' => $bundle->included_menu_item_id,
                'q' => (float) $bundle->quantity,
            ])->values(),
            'r' => $item->ingredients->mapWithKeys(
                fn (Ingredient $ingredient): array => [(string) $ingredient->id => (float) $ingredient->pivot->quantity],
            ),
        ];
    }

    private function referenceIngredient(Ingredient $ingredient): array
    {
        return [
            'id' => (string) $ingredient->id,
            'n' => $ingredient->name,
            'u' => $ingredient->unit,
            'm' => (float) $ingredient->min_threshold,
            'c' => (float) $ingredient->unit_cost,
            'cat' => $ingredient->category,
            'bin' => $ingredient->stockLots->first()?->storage_zone ?? '',
            'bc' => $ingredient->barcode,
            'ud' => 0,
            'cw' => in_array(strtolower((string) $ingredient->unit), ['kg', 'g', 'l', 'ml'], true),
            'b' => $ingredient->stockLots->map(fn ($lot): array => [
                'lot' => $lot->batch_code,
                'q' => (float) $lot->remaining_quantity,
                'exp' => $lot->expires_at?->toDateString(),
                'rd' => $lot->received_at?->toDateString() ?? $lot->created_at?->toDateString(),
            ])->values(),
        ];
    }

    private function referenceHistory(): array
    {
        $totalColumn = Schema::hasColumn('orders', 'total_amount') ? 'total_amount' : 'total';

        return collect(range(12, 0))->map(function (int $days) use ($totalColumn): float {
            $query = Order::query()
                ->notHeld()
                ->where('status', '!=', 'cancelled')
                ->whereDate('created_at', now()->subDays($days));
            if (Schema::hasColumn('orders', 'payment_status')) {
                $query->where(fn ($orders) => $orders
                    ->where('payment_status', 'paid')
                    ->orWhere('status', 'completed'));
            } else {
                $query->where('status', 'completed');
            }

            return (float) $query->sum($totalColumn);
        })->values()->all();
    }

    public function index(Request $request): View
    {
        $today = Carbon::today();
        $requestedRange = strtoupper((string) $request->query('range', '7D'));
        $range = match ($requestedRange) {
            '7', '7D' => 7,
            '30', '30D' => 30,
            'YEAR' => 'year',
            default => 7,
        };
        $rangeLabel = match ($range) {
            7 => '7D',
            30 => '30D',
            default => 'YEAR',
        };
        $rangeDescription = match ($range) {
            7 => '7 days',
            30 => '30 days',
            default => 'Last 12 months',
        };
        $periodStart = $range === 'year'
            ? $today->copy()->startOfMonth()->subMonths(11)
            : $today->copy()->subDays($range - 1)->startOfDay();
        $periodEnd = $today->copy()->endOfDay();
        $totalCol = Schema::hasColumn('orders', 'total_amount') ? 'total_amount' : 'total';

        $activeOrders = Order::query()->notHeld()->where('status', '!=', 'cancelled');
        if (Schema::hasColumn('orders', 'payment_status')) {
            $activeOrders->where(fn ($orders) => $orders
                ->where('payment_status', 'paid')
                ->orWhere('status', 'completed'));
        } else {
            $activeOrders->where('status', 'completed');
        }
        $periodOrders = (clone $activeOrders)->whereBetween('created_at', [$periodStart, $periodEnd]);
        $revenue = (float) (clone $periodOrders)->sum($totalCol);
        $periodOrderCount = (clone $periodOrders)->count();
        $averageOrderValue = $periodOrderCount > 0 ? $revenue / $periodOrderCount : null;

        $todaySales = (float) (clone $activeOrders)
            ->whereDate('created_at', $today)
            ->sum($totalCol);

        $statusNames = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
        $orderStatusCounts = collect($statusNames)->mapWithKeys(
            fn (string $status): array => [$status => Order::query()->notHeld()->where('status', $status)->count()],
        );
        $preparingOrders = $orderStatusCounts['preparing'];

        $periodItems = OrderItem::query()
            ->whereHas('order', fn ($query) => $query
                ->whereNull('held_at')
                ->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$periodStart, $periodEnd]))
            ->with('menuItem.ingredients')
            ->get();
        $unitemizedOrders = (clone $periodOrders)->doesntHave('items')->count();
        $foodCostComplete = $periodOrderCount > 0
            && $unitemizedOrders === 0
            && $periodItems->isNotEmpty();
        $foodCostTotal = 0.0;
        $foodCostRevenue = 0.0;

        foreach ($periodItems as $orderItem) {
            $menuItem = $orderItem->menuItem;
            $lineComplete = $menuItem !== null && $menuItem->ingredients->isNotEmpty();
            $lineFoodCost = 0.0;

            foreach ($menuItem?->ingredients ?? collect() as $ingredient) {
                if ($ingredient->unit_cost === null) {
                    $lineComplete = false;
                    break;
                }

                $rawQuantity = $ingredient->rawQuantityForPortion(
                    (float) $ingredient->pivot->quantity,
                );
                $lineFoodCost += $rawQuantity * (float) $ingredient->unit_cost;
            }

            if (! $lineComplete) {
                $foodCostComplete = false;
                continue;
            }

            $quantity = (int) $orderItem->quantity;
            $foodCostTotal += $lineFoodCost * $quantity;
            $foodCostRevenue += (float) $orderItem->price * $quantity;
        }

        $foodCostPercent = $foodCostComplete && $foodCostRevenue > 0
            ? ($foodCostTotal / $foodCostRevenue) * 100
            : null;
        $foodCostMessage = $periodOrderCount === 0
            ? 'No orders in this period.'
            : ($foodCostPercent === null
                ? 'Recipe cost data is incomplete for this period.'
                : 'Based on saved recipe and menu costs.');

        $wasteCost = null;
        if (Schema::hasTable('inventory_logs') && Schema::hasColumn('inventory_logs', 'cost_amount')) {
            $wasteLogs = InventoryLog::query()
                ->where('type', 'waste')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->get(['cost_amount']);
            $costedWasteLogs = $wasteLogs->filter(fn (InventoryLog $log): bool => $log->cost_amount !== null);
            $wasteCost = $wasteLogs->isEmpty()
                ? 0.0
                : ($costedWasteLogs->count() === $wasteLogs->count()
                    ? (float) $costedWasteLogs->sum('cost_amount')
                    : null);
        }

        $lowStockItems = Ingredient::query()
            ->withUsableStock()
            ->get()
            ->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock())
            ->sortBy(fn (Ingredient $ingredient): float => $ingredient->usableStock())
            ->values();
        $lowStockCount = $lowStockItems->count();
        $lowStockItems = $lowStockItems->take(5);

        $recentOrders = Order::query()->notHeld()->with('items')
            ->latest()
            ->limit(5)
            ->get();

        $salesChart = [];
        if ($range === 'year') {
            for ($i = 0; $i < 12; $i++) {
                $month = $periodStart->copy()->addMonths($i);
                $monthEnd = $month->copy()->endOfMonth();
                if ($monthEnd->greaterThan($periodEnd)) {
                    $monthEnd = $periodEnd;
                }
                $salesChart[] = [
                    'label' => $month->format('M'),
                    'title' => $month->format('F Y'),
                    'total' => (float) (clone $activeOrders)
                        ->whereBetween('created_at', [$month->startOfMonth(), $monthEnd])
                        ->sum($totalCol),
                ];
            }
        } else {
            for ($i = $range - 1; $i >= 0; $i--) {
                $date = $today->copy()->subDays($i);
                $salesChart[] = [
                    'label' => $date->format('m-d'),
                    'title' => $date->format('M j'),
                    'total' => (float) (clone $activeOrders)
                        ->whereDate('created_at', $date)
                        ->sum($totalCol),
                ];
            }
        }

        $maxSale = max(array_column($salesChart, 'total')) ?: 1.0;
        $administratorName = $request->user()->name;

        return view('admin.dashboard', compact(
            'averageOrderValue',
            'administratorName',
            'foodCostMessage',
            'foodCostPercent',
            'todaySales',
            'orderStatusCounts',
            'periodOrderCount',
            'rangeLabel',
            'rangeDescription',
            'revenue',
            'wasteCost',
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
        $query = Order::query()->notHeld()->with('items');

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
