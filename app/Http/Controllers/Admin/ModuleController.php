<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\AdminSettings;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function recipes(): View
    {
        $items = MenuItem::query()->with(['category', 'ingredients'])->orderBy('name')->get();
        $ingredients = Ingredient::query()->orderBy('name')->get();

        return view('admin.recipes', compact('items', 'ingredients'));
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

    public function waste(): View
    {
        $ingredients = Ingredient::query()->orderBy('name')->get();
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
            'reason' => ['required', Rule::in(['Pre-Cook Spoilage', 'Over-Prep Waste', 'Boodle Return', 'Other'])],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        try {
            $inventory->recordWaste(
                Ingredient::findOrFail($validated['ingredient_id']),
                (float) $validated['quantity'],
                $validated['reason'],
                trim($validated['notes']),
                $request->user(),
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Waste logged and stock updated.');
    }

    public function analytics(): View
    {
        $today = Carbon::today();
        $totalCol = Schema::hasColumn('orders', 'total_amount') ? 'total_amount' : 'total';

        $todaySales = (float) Order::whereDate('created_at', $today)
            ->whereNotIn('status', ['cancelled'])
            ->sum($totalCol);
        $weekSales = (float) Order::where('created_at', '>=', $today->copy()->subDays(6))
            ->whereNotIn('status', ['cancelled'])
            ->sum($totalCol);

        $byStatus = [
            'pending' => Order::where('status', 'pending')->count(),
            'preparing' => Order::where('status', 'preparing')->count(),
            'ready' => Order::where('status', 'ready')->count(),
            'completed' => Order::where('status', 'completed')->count(),
        ];

        $salesChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $salesChart[] = [
                'label' => $date->format('D'),
                'total' => (float) Order::whereDate('created_at', $date)
                    ->whereNotIn('status', ['cancelled'])
                    ->sum($totalCol),
            ];
        }
        $maxSale = max(array_column($salesChart, 'total') ?: [0]) ?: 1;

        $nameCol = Schema::hasColumn('order_items', 'item_name') ? 'item_name' : 'name';
        $topItems = DB::table('order_items')
            ->select($nameCol.' as item_name', DB::raw('SUM(quantity) as qty'))
            ->groupBy($nameCol)
            ->orderByDesc('qty')
            ->limit(8)
            ->get();

        return view('admin.analytics', compact(
            'todaySales', 'weekSales', 'byStatus', 'salesChart', 'maxSale', 'topItems'
        ));
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
        $this->ensureReservationsTable();

        $reservations = Reservation::query()->orderBy('reserved_at')->get();

        return view('admin.reservations', compact('reservations'));
    }

    public function storeReservation(Request $request): RedirectResponse
    {
        $this->ensureReservationsTable();

        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
            'party_size' => ['required', 'integer', 'min:1', 'max:40'],
            'reserved_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        Reservation::create([
            ...$validated,
            'status' => 'upcoming',
        ]);

        return back()->with('status', 'Reservation added.');
    }

    public function updateReservation(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['upcoming', 'seated', 'completed', 'cancelled'])],
        ]);

        $reservation->update($validated);

        return back()->with('status', 'Reservation updated.');
    }

    public function chatbot(Request $request): View
    {
        $messages = $request->session()->get('admin_chat', []);
        $articles = $this->knowledgeBase();

        return view('admin.chatbot', compact('messages', 'articles'));
    }

    public function chatbotMessage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $history = $request->session()->get('admin_chat', []);
        $history[] = ['role' => 'user', 'text' => $validated['message']];
        $history[] = ['role' => 'assistant', 'text' => $this->answerChat($validated['message'])];
        $request->session()->put('admin_chat', array_slice($history, -40));

        return back();
    }

    public function storeKnowledge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $articles = $request->session()->get('admin_knowledge', []);
        $articles[] = $validated;
        $request->session()->put('admin_knowledge', $articles);

        return back()->with('status', 'Knowledge article added.');
    }

    public function users(): View
    {
        $users = User::query()->orderBy('name')->get();

        return view('admin.users', compact('users'));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'string', 'min:8', 'max:80'],
        ]);

        User::create($validated);

        return back()->with('status', 'User account created.');
    }

    public function activity(): View
    {
        $logs = InventoryLog::query()
            ->with(['ingredient', 'user'])
            ->latest()
            ->limit(50)
            ->get();

        $recentOrders = Order::query()->latest('updated_at')->limit(20)->get();

        return view('admin.activity', compact('logs', 'recentOrders'));
    }

    public function settings(): View
    {
        $settings = AdminSettings::all();

        return view('admin.settings', compact('settings'));
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
        ]);

        AdminSettings::put([
            'restaurant_name' => $validated['restaurant_name'],
            'tagline' => $validated['tagline'],
            'open_time' => $validated['open_time'],
            'close_time' => $validated['close_time'],
            'low_stock_alerts' => $request->boolean('low_stock_alerts'),
            'order_alerts' => $request->boolean('order_alerts'),
        ]);

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
            ['title' => 'Order flow', 'body' => 'Orders move Pending → Preparing → Ready → Completed. Admins can advance status from Order management.'],
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
            return 'Order management lists every ticket. Use the status tabs, then Mark Preparing, Ready, or Completed.';
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
