<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\ActivityLog;
use App\Models\AdminAlertRule;
use App\Models\ChatbotKnowledgeSource;
use App\Models\EventBlackoutDate;
use App\Models\EventInquiry;
use App\Models\EventInquiryNote;
use App\Models\EventPackage;
use App\Models\Faq;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemBundle;
use App\Models\MenuModifierGroup;
use App\Models\MenuTag;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Models\UnansweredQuestion;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\AdminSettings;
use App\Support\InventoryTaxonomy;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function referenceReservation(Request $request, EventInquiry $reservation): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'under_review', 'approved', 'declined'])],
            'event_package_id' => ['nullable', 'integer', 'exists:event_packages,id'],
        ]);
        $reservation->update($validated);
        return response()->json(['ok' => true]);
    }

    public function referenceReservationNote(Request $request, EventInquiry $reservation): JsonResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $reservation->internalNotes()->create(['created_by' => $request->user()->id, 'note' => trim($validated['note'])]);
        return response()->json(['ok' => true]);
    }

    public function referenceFaq(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
        ]);
        $faq = Faq::create([...$validated, 'is_active' => true, 'sort_order' => (int) Faq::max('sort_order') + 1]);
        return response()->json(['id' => $faq->id]);
    }

    public function referenceAnswer(Request $request, UnansweredQuestion $question): JsonResponse
    {
        $validated = $request->validate(['answer' => ['required', 'string', 'max:5000']]);
        $faq = Faq::create(['question' => $question->question, 'answer' => $validated['answer'], 'is_active' => true, 'sort_order' => (int) Faq::max('sort_order') + 1]);
        $question->update(['status' => 'answered', 'faq_id' => $faq->id, 'answered_at' => now()]);
        return response()->json(['id' => $faq->id]);
    }

    public function referenceUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'min:8', 'max:80'],
        ]);
        $role = \App\Models\Role::query()->where('name', $validated['role'])->first();
        $legacyRole = match ($role?->key) {
            'restaurant_manager', 'super_admin' => 'admin',
            'head_chef' => 'kitchen',
            'cashier' => 'cashier',
            'inventory_user' => 'inventory',
            default => 'admin',
        };
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $legacyRole,
            'role_id' => $role?->id,
            'is_active' => true,
        ]);
        return response()->json(['id' => $user->id]);
    }

    public function referenceUserToggle(User $user): JsonResponse
    {
        abort_if((int) $user->id === (int) auth()->id(), 422, 'You cannot deactivate your own account.');
        $user->update(['is_active' => ! $user->is_active]);
        return response()->json(['active' => (bool) $user->is_active]);
    }

    public function referencePermission(Request $request, \App\Models\Role $role): JsonResponse
    {
        $validated = $request->validate(['module_key' => ['required', 'string', 'max:50'], 'can_view' => ['required', 'boolean']]);
        abort_if($role->key === 'super_admin', 422, 'Super Admin permissions cannot be changed.');
        $role->permissions()->updateOrCreate(['module_key' => $validated['module_key']], ['can_view' => $validated['can_view']]);
        return response()->json(['ok' => true]);
    }

    public function referenceRule(Request $request, AdminAlertRule $rule): JsonResponse
    {
        $rule->update(['is_enabled' => $request->boolean('enabled')]);
        return response()->json(['enabled' => (bool) $rule->is_enabled]);
    }

    public function referenceSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vat' => ['required', 'numeric', 'min:0', 'max:100'],
            'svc' => ['required', 'numeric', 'min:0', 'max:100'],
            'cur' => ['required', 'string', 'max:10'],
            'open' => ['required', 'date_format:H:i'],
            'close' => ['required', 'date_format:H:i'],
            'black' => ['nullable', 'string', 'max:2000'],
            'qrEnabled' => ['required', 'boolean'],
        ]);
        AdminSettings::put([
            'vat_percent' => (float) $validated['vat'],
            'service_charge_percent' => (float) $validated['svc'],
            'currency' => $validated['cur'],
            'open_time' => $validated['open'],
            'close_time' => $validated['close'],
            'blackout_dates' => $validated['black'] ?? '',
            'qr_enabled' => (bool) $validated['qrEnabled'],
        ]);
        foreach ([
            'vat_percent' => $validated['vat'],
            'service_charge_percent' => $validated['svc'],
            'currency' => $validated['cur'],
            'blackout_dates' => $validated['black'] ?? '',
            'qr_enabled' => $validated['qrEnabled'] ? '1' : '0',
        ] as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value, 'group' => 'restaurant']);
        }
        return response()->json(['ok' => true]);
    }

    public function referenceActivity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', 'max:80'],
            'action' => ['required', 'string', 'max:120'],
        ]);
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'module' => $validated['module'],
            'action' => $validated['action'],
        ]);
        return response()->json(['ok' => true]);
    }

    public function referenceMenuStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'category' => ['nullable', 'string', 'max:100'],
            'collection_type' => ['nullable', 'string', 'max:24'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:50'],
            'variants' => ['array'],
            'variants.*.name' => ['required', 'string', 'max:100'],
            'variants.*.price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'variants.*.portion_factor' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'variants.*.stock_quantity' => ['required', 'numeric', 'min:0', 'max:999999'],
            'variants.*.min_stock_quantity' => ['required', 'numeric', 'min:0', 'max:999999'],
            'modifiers' => ['array'],
            'modifiers.*.name' => ['required', 'string', 'max:100'],
            'modifiers.*.is_required' => ['boolean'],
            'modifiers.*.options' => ['array'],
            'modifiers.*.options.*.name' => ['required', 'string', 'max:100'],
            'modifiers.*.options.*.price_delta' => ['required', 'numeric', 'min:-999999', 'max:999999'],
            'bundles' => ['array'],
            'bundles.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'bundles.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999'],
        ]);

        $item = DB::transaction(function () use ($validated): MenuItem {
            $category = MenuCategory::query()->firstOrCreate(
                ['name' => $validated['category'] ?: 'Uncategorized'],
                ['slug' => Str::slug($validated['category'] ?: 'Uncategorized'), 'is_active' => true],
            );
            $item = isset($validated['id'])
                ? MenuItem::query()->findOrFail($validated['id'])
                : new MenuItem(['slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(4))]);
            $item->fill([
                'menu_category_id' => $category->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'collection_type' => $validated['collection_type'] ?? 'Regular',
                'is_active' => $validated['is_active'] ?? true,
                'is_available' => $validated['is_active'] ?? true,
            ])->save();
            $tagIds = collect($validated['tags'] ?? [])
                ->map(fn (string $tag): MenuTag => MenuTag::query()->firstOrCreate(['name' => trim($tag)]))
                ->filter(fn (MenuTag $tag): bool => $tag->name !== '')
                ->map(fn (MenuTag $tag): int => $tag->id)
                ->values()
                ->all();
            $item->tags()->sync($tagIds);

            $item->variants()->delete();
            foreach ($validated['variants'] ?? [] as $variant) {
                $item->variants()->create([
                    'name' => $variant['name'],
                    'price' => $variant['price'],
                    'portion_factor' => $variant['portion_factor'],
                    'stock_quantity' => $variant['stock_quantity'],
                    'min_stock_quantity' => $variant['min_stock_quantity'],
                    'is_active' => true,
                ]);
            }
            $item->modifierGroups()->delete();
            foreach ($validated['modifiers'] ?? [] as $groupIndex => $group) {
                $modifierGroup = $item->modifierGroups()->create([
                    'name' => $group['name'],
                    'is_required' => $group['is_required'] ?? false,
                    'sort_order' => $groupIndex,
                ]);
                foreach ($group['options'] ?? [] as $optionIndex => $option) {
                    $modifierGroup->options()->create([
                        'name' => $option['name'],
                        'price_delta' => $option['price_delta'],
                        'is_active' => true,
                        'sort_order' => $optionIndex,
                    ]);
                }
            }
            $item->bundles()->delete();
            foreach ($validated['bundles'] ?? [] as $bundle) {
                if ((int) $bundle['menu_item_id'] === $item->id) {
                    continue;
                }
                $item->bundles()->create([
                    'included_menu_item_id' => $bundle['menu_item_id'],
                    'quantity' => $bundle['quantity'],
                ]);
            }

            return $item;
        });

        return response()->json(['id' => $item->id]);
    }

    public function referenceRecipeTarget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipe_target_percent' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);
        AdminSettings::put(['recipe_target_percent' => (float) $validated['recipe_target_percent']]);

        return response()->json(['recipe_target_percent' => (float) $validated['recipe_target_percent']]);
    }

    public function referenceMenuArchive(MenuItem $item): JsonResponse
    {
        $this->archiveMenuItem($item);

        return response()->json(['ok' => true]);
    }

    public function referenceMenuRestore(MenuItem $item): JsonResponse
    {
        $this->restoreMenuItem($item);

        return response()->json(['ok' => true]);
    }

    public function referenceMenuDelete(MenuItem $item): JsonResponse
    {
        $this->destroyMenuItem($item);

        return response()->json(['ok' => true]);
    }

    public function referenceRecipe(Request $request, MenuItem $item): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:9999'],
        ]);
        $item->ingredients()->syncWithoutDetaching([
            $validated['ingredient_id'] => ['quantity' => $validated['quantity']],
        ]);

        return response()->json(['ok' => true]);
    }

    public function referenceRecipeDelete(MenuItem $item, Ingredient $ingredient): JsonResponse
    {
        $item->ingredients()->detach($ingredient->id);

        return response()->json(['ok' => true]);
    }

    public function recipes(): View
    {
        $items = MenuItem::query()->with(['category', 'ingredients'])->orderBy('name')->get();
        $ingredients = Ingredient::query()->orderBy('name')->get();
        $targetPercent = (float) (AdminSettings::all()['recipe_target_percent'] ?? 30);

        return view('admin.recipes', compact('items', 'ingredients', 'targetPercent'));
    }

    public function storeRecipe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:9999'],
        ]);

        $item = MenuItem::query()->findOrFail($validated['menu_item_id']);
        $item->ingredients()->syncWithoutDetaching([
            $validated['ingredient_id'] => ['quantity' => $validated['quantity']],
        ]);

        return back()->with('status', 'Recipe line saved.');
    }

    public function updateRecipeTarget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recipe_target_percent' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);
        AdminSettings::put(['recipe_target_percent' => (float) $validated['recipe_target_percent']]);

        return back()->with('status', 'Recipe cost target updated.');
    }

    public function destroyRecipeLine(MenuItem $item, Ingredient $ingredient): RedirectResponse
    {
        $item->ingredients()->detach($ingredient->id);

        return back()->with('status', 'Recipe ingredient removed.');
    }

    public function waste(): View
    {
        $ingredients = Ingredient::query()->usedByCurrentMenu()->orderBy('name')->get();
        $logs = InventoryLog::query()
            ->with(['ingredient', 'user'])
            ->where('type', 'waste')
            ->latest()
            ->limit(40)
            ->get();

        return view('admin.waste', compact('ingredients', 'logs'));
    }

    public function storeWaste(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'stage' => ['required', Rule::in(InventoryTaxonomy::wasteStages())],
            'reason' => ['required', Rule::in(InventoryTaxonomy::wasteReasons())],
            'other_reason' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn (): bool => $request->input('reason') === InventoryTaxonomy::OTHER_REASON)],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        try {
            $reason = $validated['reason'] === InventoryTaxonomy::OTHER_REASON
                ? 'Others: '.trim((string) $validated['other_reason'])
                : $validated['reason'];
            $inventory->recordWaste(
                Ingredient::findOrFail($validated['ingredient_id']),
                (float) $validated['quantity'],
                $reason,
                $reason.' · '.trim($validated['notes']),
                $request->user(),
                $validated['stage'],
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Waste logged and stock updated.');
    }

    public function analytics(): View
    {
        $requestedRange = strtoupper((string) request()->query('range', '7D'));
        $days = match ($requestedRange) {
            '30D' => 30,
            'YEAR' => 365,
            default => 7,
        };
        $rangeLabel = $days === 365 ? 'YEAR' : $days.'D';
        $today = Carbon::today();
        $start = $today->copy()->subDays($days - 1)->startOfDay();
        $totalCol = Schema::hasColumn('orders', 'total_amount') ? 'total_amount' : 'total';
        $orders = Order::query()->notHeld()->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $today->copy()->endOfDay()]);
        $totalSales = (float) (clone $orders)->sum($totalCol);
        $totalOrders = (clone $orders)->count();
        $averageOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : null;
        $itemsPerOrder = $totalOrders > 0
            ? (float) (clone $orders)->withSum('items', 'quantity')->get()->avg('items_sum_quantity')
            : null;
        $byStatus = Order::query()->notHeld()->whereBetween('created_at', [$start, $today->copy()->endOfDay()])
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $salesChart = [];
        $chartStart = $days === 365 ? $today->copy()->startOfMonth()->subMonths(11) : $start;
        $points = $days === 365 ? 12 : $days;
        for ($i = 0; $i < $points; $i++) {
            $point = $days === 365 ? $chartStart->copy()->addMonths($i) : $chartStart->copy()->addDays($i);
            $end = $days === 365 ? $point->copy()->endOfMonth() : $point->copy()->endOfDay();
            $salesChart[] = [
                'label' => $days === 365 ? $point->format('M') : $point->format('M j'),
                'total' => (float) Order::query()->notHeld()->where('status', '!=', 'cancelled')
                    ->whereBetween('created_at', [$point->copy()->startOfDay(), $end])->sum($totalCol),
            ];
        }
        $maxSale = max(array_column($salesChart, 'total') ?: [0]) ?: 1;
        $lines = OrderItem::query()->whereHas('order', fn ($query) => $query
            ->notHeld()->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $today->copy()->endOfDay()]))
            ->with('menuItem.category')->get()
            ->filter(fn (OrderItem $line): bool => $line->menuItem !== null
                && $line->menuItem->is_active
                && $line->menuItem->archived_at === null)
            ->values();
        $salesByCategory = $lines->groupBy(fn (OrderItem $line): string =>
            $line->menuItem?->category?->name ?? $line->category_name_snapshot ?? 'Uncategorized'
        )->map(fn (Collection $categoryLines): float =>
            (float) $categoryLines->sum(fn (OrderItem $line): float => (float) $line->price * $line->quantity)
        )->sortDesc();
        $topProducts = $lines->groupBy(fn (OrderItem $line): string => $line->item_name)
            ->map(fn (Collection $productLines): array => [
                'name' => $productLines->first()->item_name,
                'units' => (int) $productLines->sum('quantity'),
                'revenue' => (float) $productLines->sum(fn (OrderItem $line): float => (float) $line->price * $line->quantity),
            ])->sortByDesc('units')->values()->take(10);
        $paymentMethods = (clone $orders)->selectRaw("COALESCE(payment_method, 'Unspecified') as method, SUM({$totalCol}) as total")
            ->groupBy('method')->orderByDesc('total')->get();
        $allIngredients = Ingredient::query()->usedByCurrentMenu()->get();
        $inventoryStatus = [
            'OK' => $allIngredients->filter(fn (Ingredient $ingredient): bool => ! $ingredient->isLowStock() && $ingredient->usableStock() > 0)->count(),
            'Low' => $allIngredients->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock() && $ingredient->usableStock() > 0)->count(),
            'Out' => $allIngredients->filter(fn (Ingredient $ingredient): bool => $ingredient->usableStock() <= 0)->count(),
        ];

        return view('admin.analytics', compact(
            'rangeLabel', 'totalSales', 'totalOrders', 'averageOrderValue', 'itemsPerOrder',
            'byStatus', 'salesChart', 'maxSale', 'salesByCategory', 'topProducts',
            'paymentMethods', 'inventoryStatus'
        ));
    }

    public function archived(): View
    {
        $items = MenuItem::query()->with('category')->whereNotNull('archived_at')->latest('archived_at')->get();

        return view('admin.archived', compact('items'));
    }

    public function slowMoving(): View
    {
        $items = MenuItem::query()->with('category')->orderBy('name')->get();
        $sold = OrderItem::query()->whereHas('order', fn ($query) => $query
            ->notHeld()->where('status', '!=', 'cancelled')->where('created_at', '>=', now()->subDays(120)))
            ->select('menu_item_id', DB::raw('SUM(quantity) as units_sold'))
            ->groupBy('menu_item_id')->pluck('units_sold', 'menu_item_id');
        $rows = $items->map(fn (MenuItem $item): array => ['item' => $item, 'units' => (int) ($sold[$item->id] ?? 0)])
            ->sortBy('units')->values();

        return view('admin.slow-moving', [
            'rows' => $rows,
            'noSales' => $rows->where('units', 0)->count(),
            'verySlow' => $rows->whereBetween('units', [1, 20])->count(),
            'slow' => $rows->whereBetween('units', [21, 39])->count(),
            'fast' => $rows->where('units', '>=', 40)->count(),
            'wasteAtRisk' => (float) InventoryLog::query()->where('type', 'waste')->sum('cost_amount'),
        ]);
    }

    public function archiveMenuItem(MenuItem $item): RedirectResponse
    {
        $item->update(['is_active' => false, 'is_available' => false, 'archived_at' => now()]);

        return back()->with('status', $item->name.' was archived.');
    }

    public function restoreMenuItem(MenuItem $item): RedirectResponse
    {
        $item->update(['is_active' => true, 'is_available' => true, 'archived_at' => null]);

        return back()->with('status', $item->name.' was restored.');
    }

    public function forecasting(): View
    {
        $totalCol = Schema::hasColumn('orders', 'total_amount') ? 'total_amount' : 'total';
        $daily = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $daily[] = [
                'label' => $date->format('D j'),
                'orders' => Order::whereDate('created_at', $date)->whereNotIn('status', ['cancelled'])->count(),
                'sales' => (float) Order::whereDate('created_at', $date)->whereNotIn('status', ['cancelled'])->sum($totalCol),
            ];
        }

        $avgOrders = collect($daily)->avg('orders') ?: 0;
        $avgSales = collect($daily)->avg('sales') ?: 0;

        $forecast = [];
        for ($i = 1; $i <= 7; $i++) {
            $date = Carbon::today()->addDays($i);
            $forecast[] = [
                'label' => $date->format('D j'),
                'orders' => (int) round($avgOrders),
                'sales' => round($avgSales, 2),
            ];
        }

        $lowStock = Ingredient::query()
            ->withUsableStock()
            ->get()
            ->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock())
            ->sortBy(fn (Ingredient $ingredient): float => $ingredient->usableStock())
            ->values();

        return view('admin.forecasting', compact('daily', 'forecast', 'avgOrders', 'avgSales', 'lowStock'));
    }

    public function reservations(): View
    {
        $tab = request()->query('tab', 'Inquiries');
        $inquiries = EventInquiry::query()
            ->with(['package', 'internalNotes.user'])
            ->when($tab === 'Booked', fn ($query) => $query->whereIn('status', EventInquiry::BOOKED_STATUSES))
            ->when($tab === 'Archived', fn ($query) => $query->where('status', 'declined'))
            ->when($tab === 'Inquiries', fn ($query) => $query->whereNotIn('status', ['declined', ...EventInquiry::BOOKED_STATUSES]))
            ->orderBy('event_date')
            ->get();
        $blackouts = EventBlackoutDate::query()->orderBy('date')->get();
        $packages = EventPackage::query()->where('is_active', true)->with('menuItems.ingredients')->orderBy('name')->get();

        return view('admin.reservations', compact('inquiries', 'blackouts', 'packages', 'tab'));
    }

    public function storeReservation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'event_type' => ['required', Rule::in(EventInquiry::EVENT_TYPES)],
            'preferred_time' => ['required', Rule::in(EventInquiry::PREFERRED_TIMES)],
            'guest_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'preferred_package' => ['nullable', Rule::in(EventInquiry::PACKAGE_OPTIONS)],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'special_requests' => ['nullable', 'string', 'max:5000'],
        ]);
        $validated['reference'] = 'EV-'.strtoupper(Str::random(8));
        $validated['status'] = EventInquiry::STATUS_PENDING;
        EventInquiry::create($validated);

        return back()->with('status', 'Reservation added.');
    }

    public function updateReservation(Request $request, EventInquiry $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'under_review', 'approved', 'declined'])],
            'event_package_id' => ['nullable', 'integer', 'exists:event_packages,id'],
        ]);

        $reservation->update($validated);

        return back()->with('status', 'Reservation updated.');
    }

    public function addInquiryNote(Request $request, EventInquiry $reservation): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $reservation->internalNotes()->create(['created_by' => $request->user()->id, 'note' => trim($validated['note'])]);

        return back()->with('status', 'Internal note added.');
    }

    public function toggleBlackout(Request $request): RedirectResponse
    {
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $blackout = EventBlackoutDate::query()->whereDate('date', $validated['date'])->first();
        if ($blackout) {
            $blackout->delete();
        } else {
            EventBlackoutDate::create(['date' => $validated['date'], 'created_by' => $request->user()->id]);
        }

        return back()->with('status', $blackout ? 'Date unblocked.' : 'Date blocked.');
    }

    public function chatbot(Request $request): View
    {
        $faqs = Faq::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $unanswered = UnansweredQuestion::query()->where('status', 'unanswered')->latest()->get();
        $sources = ChatbotKnowledgeSource::query()->latest()->get();
        $settings = [
            'persona' => SystemSetting::query()->where('key', 'chatbot.persona')->value('value') ?: 'Warm & professional',
            'guardrail_calendar' => SystemSetting::query()->where('key', 'chatbot.guardrail_calendar')->value('value') !== '0',
        ];

        return view('admin.chatbot', compact('faqs', 'unanswered', 'sources', 'settings'));
    }

    public function chatbotMessage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        UnansweredQuestion::create(['question' => trim($validated['message']), 'status' => 'unanswered']);

        return back()->with('status', 'Question added to unanswered questions.');
    }

    public function storeKnowledge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        ChatbotKnowledgeSource::create(['title' => $validated['title'], 'content' => $validated['body'], 'is_active' => true, 'uploaded_by' => $request->user()->id]);

        return back()->with('status', 'Knowledge article added.');
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:5000'],
        ]);
        Faq::create([...$validated, 'is_active' => true, 'sort_order' => (int) Faq::max('sort_order') + 1]);

        return back()->with('status', 'FAQ added.');
    }

    public function answerQuestion(Request $request, UnansweredQuestion $question): RedirectResponse
    {
        $validated = $request->validate(['answer' => ['required', 'string', 'max:5000']]);
        $faq = Faq::create(['question' => $question->question, 'answer' => $validated['answer'], 'is_active' => true, 'sort_order' => (int) Faq::max('sort_order') + 1]);
        $question->update(['status' => 'answered', 'faq_id' => $faq->id, 'answered_at' => now()]);

        return back()->with('status', 'Answer saved to the FAQ.');
    }

    public function updateChatbotSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate(['persona' => ['required', 'string', 'max:80']]);
        SystemSetting::updateOrCreate(['key' => 'chatbot.persona'], ['value' => $validated['persona'], 'group' => 'chatbot']);
        SystemSetting::updateOrCreate(['key' => 'chatbot.guardrail_calendar'], ['value' => $request->boolean('guardrail_calendar') ? '1' : '0', 'group' => 'chatbot']);

        return back()->with('status', 'Chatbot settings saved.');
    }

    public function users(): View
    {
        $users = User::query()->with('assignedRole')->orderBy('name')->get();
        $roles = \App\Models\Role::query()->with('permissions')->orderBy('id')->get();

        return view('admin.users', compact('users', 'roles'));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'string', 'min:8', 'max:80'],
        ]);

        User::create([...$validated, 'is_active' => true]);

        return back()->with('status', 'User account created.');
    }

    public function toggleUser(User $user): RedirectResponse
    {
        if ((int) $user->id === (int) auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', $user->name.' is now '.($user->is_active ? 'active' : 'deactivated').'.');
    }

    public function updatePermission(Request $request, \App\Models\Role $role): RedirectResponse
    {
        if ($role->key === 'super_admin') {
            return back()->with('error', 'Super Admin permissions cannot be changed.');
        }
        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:50'],
            'can_view' => ['required', 'boolean'],
        ]);
        $role->permissions()->updateOrCreate(
            ['module_key' => $validated['module_key']],
            ['can_view' => $request->boolean('can_view')],
        );

        return back()->with('status', 'Role permission updated.');
    }

    public function activity(): View
    {
        $logs = ActivityLog::query()->with('user')->latest()->limit(100)->get();

        $recentOrders = Order::query()->notHeld()->latest('updated_at')->limit(20)->get();

        return view('admin.activity', compact('logs', 'recentOrders'));
    }

    public function settings(): View
    {
        $settings = AdminSettings::all();
        $databaseSettings = SystemSetting::query()->whereIn('key', [
            'vat_percent', 'service_charge_percent', 'currency', 'blackout_dates', 'qr_enabled',
        ])->pluck('value', 'key')->all();

        return view('admin.settings', compact('settings', 'databaseSettings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:80'],
            'tagline' => ['required', 'string', 'max:80'],
            'open_time' => ['required', 'date_format:H:i'],
            'close_time' => ['required', 'date_format:H:i'],
            'low_stock_alerts' => ['sometimes', 'boolean'],
            'order_alerts' => ['sometimes', 'boolean'],
            'vat_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'service_charge_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'blackout_dates' => ['nullable', 'string', 'max:2000'],
            'qr_enabled' => ['sometimes', 'boolean'],
            'payment_gateway_key' => ['nullable', 'string', 'max:500'],
        ]);

        AdminSettings::put([
            'restaurant_name' => $validated['restaurant_name'],
            'tagline' => $validated['tagline'],
            'open_time' => $validated['open_time'],
            'close_time' => $validated['close_time'],
            'low_stock_alerts' => $request->boolean('low_stock_alerts'),
            'order_alerts' => $request->boolean('order_alerts'),
        ]);
        foreach ([
            'vat_percent' => $validated['vat_percent'],
            'service_charge_percent' => $validated['service_charge_percent'],
            'currency' => $validated['currency'],
            'blackout_dates' => $validated['blackout_dates'] ?? '',
            'qr_enabled' => $request->boolean('qr_enabled') ? '1' : '0',
        ] as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value, 'group' => 'restaurant']);
        }
        if (($validated['payment_gateway_key'] ?? '') !== '') {
            SystemSetting::updateOrCreate(['key' => 'payment_gateway_key'], ['value' => $validated['payment_gateway_key'], 'is_secret' => true, 'group' => 'payments']);
        }

        return back()->with('status', 'Settings saved.');
    }

    public function storeMenuItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'menu_category_id' => ['nullable', 'integer', 'exists:menu_categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $categoryId = $validated['menu_category_id'] ?? MenuCategory::query()->value('id');

        MenuItem::create([
            'menu_category_id' => $categoryId,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(4)),
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'is_active' => true,
            'is_available' => true,
        ]);

        return back()->with('status', 'Menu item added.');
    }

    public function toggleMenuItem(MenuItem $item): RedirectResponse
    {
        $item->update(['is_available' => ! $item->is_available]);

        return back()->with('status', $item->name.' is now '.($item->is_available ? 'available' : 'unavailable').'.');
    }

    public function destroyMenuItem(MenuItem $item): RedirectResponse
    {
        $name = $item->name;
        $item->delete();

        return back()->with('status', $name.' was deleted. Previous order records are preserved.');
    }

    public function receiveBarcode(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],
        ]);

        $code = strtoupper(trim($validated['code']));
        $ingredient = null;

        if (preg_match('/^ING-?0*([0-9]+)$/', $code, $matches)) {
            $ingredient = Ingredient::query()->find((int) $matches[1]);
        }

        if ($ingredient === null) {
            $ingredient = Ingredient::query()->whereRaw('UPPER(name) = ?', [$code])->first();
        }

        if ($ingredient === null) {
            return back()->with('error', 'No ingredient matched that barcode or name.');
        }

        $inventory->logMovement(
            $ingredient,
            'stock_in',
            (float) $validated['quantity'],
            $request->user(),
            'Barcode receiving',
        );

        return back()->with('status', "Received {$validated['quantity']} {$ingredient->unit} of {$ingredient->name}.");
    }

    private function knowledgeBase(): array
    {
        $defaults = [
            ['title' => 'Opening hours', 'body' => 'Passenger Seat is open daily from the hours set in System settings. Kitchen last call is 30 minutes before close.'],
            ['title' => 'Low stock policy', 'body' => 'Ingredients at or below the minimum threshold appear in Inventory and the notifications panel. Receive stock from Barcode receiving or Inventory.'],
            ['title' => 'Order flow', 'body' => 'Orders move Pending → Preparing → Ready → Served. Admins can advance status from Order management.'],
            ['title' => 'Wi-Fi', 'body' => 'Guest Wi-Fi is posted at the counter. Staff accounts use the staff login page, not the public site.'],
        ];

        return array_merge($defaults, session('admin_knowledge', []));
    }

    private function answerChat(string $message): string
    {
        $hay = Str::lower($message);

        foreach ($this->knowledgeBase() as $article) {
            if (Str::contains($hay, Str::lower($article['title'])) || Str::contains(Str::lower($article['body']), explode(' ', $hay)[0] ?? '')) {
                if (Str::contains($hay, Str::of($article['title'])->explode(' ')->map(fn ($w) => Str::lower($w))->filter(fn ($w) => strlen($w) > 3)->all())) {
                    return $article['body'];
                }
            }
        }

        if (Str::contains($hay, ['hour', 'open', 'close'])) {
            $s = AdminSettings::all();

            return "We are open {$s['open_time']}–{$s['close_time']}.";
        }
        if (Str::contains($hay, ['stock', 'inventory'])) {
            return 'Check Inventory management for live stock and low-stock alerts. You can receive packages from Barcode receiving.';
        }
        if (Str::contains($hay, ['order', 'kitchen'])) {
            return 'Order management lists every ticket. Use the status tabs, then Mark Preparing, Ready, or Served.';
        }
        if (Str::contains($hay, ['wifi', 'wi-fi'])) {
            return 'Guest Wi-Fi is posted at the counter. Staff should use their own login.';
        }

        return 'I could not find an exact match. Add an article in the knowledge base, or ask about hours, stock, or orders.';
    }

    private function ensureReservationsTable(): void
    {
        if (Schema::hasTable('reservations')) {
            return;
        }

        Schema::create('reservations', function ($table): void {
            $table->id();
            $table->string('guest_name', 120);
            $table->string('guest_phone', 30)->nullable();
            $table->unsignedTinyInteger('party_size');
            $table->dateTime('reserved_at');
            $table->string('status')->default('upcoming')->index();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }
}
