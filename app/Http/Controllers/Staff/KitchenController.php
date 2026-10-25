<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\KitchenOrderResource;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\OrderService;
use App\Support\InventoryTaxonomy;

class KitchenController extends Controller
{
    public function index(): View
    {
        return view('kitchen.index');
    }

    public function orders(): JsonResponse
    {
        $orders = Order::query()
            ->notHeld()
            ->whereIn('status', ['pending', 'preparing', 'ready', 'served'])
            ->with(['items', 'statusHistories'])
            ->oldest('created_at')
            ->get();

        return response()->json([
            'orders' => KitchenOrderResource::collection($orders)->resolve(),
            'server_now' => now()->getTimestamp() * 1000,
        ]);
    }

    public function transition(Request $request, Order $order, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:preparing,ready,served'],
        ]);

        try {
            $orders->transition($order, $data['status'], $request->user());
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $updatedOrder = Order::query()
            ->with(['items', 'statusHistories'])
            ->findOrFail($order->id);

        return response()->json([
            'order' => (new KitchenOrderResource($updatedOrder))->resolve($request),
            'server_now' => now()->getTimestamp() * 1000,
        ]);
    }

    public function inventoryJson(): JsonResponse
    {
        $ingredients = Ingredient::query()
            ->usedByCurrentMenu()
            ->withUsableStock()
            ->withSum([
                'orderReservations as reserved_quantity' => fn ($query) => $query->where('status', 'reserved'),
            ], 'quantity')
            ->with(['stockLots' => fn ($query) => $query
                ->where('remaining_quantity', '>', 0)
                ->orderBy('received_at')
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('id')])
            ->orderBy('name')
            ->get();
        $ingredientIndexes = $ingredients->values()->mapWithKeys(
            fn (Ingredient $ingredient, int $index): array => [$ingredient->id => $index],
        );
        $menuItems = MenuItem::query()
            ->withStockAvailability()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $today = now('Asia/Manila')->startOfDay();

        $availability = [];
        $recipes = $menuItems->map(function (MenuItem $item) use (&$availability, $ingredientIndexes): array {
            $missing = $item->ingredients->filter(function (Ingredient $ingredient): bool {
                $usable = min((float) $ingredient->current_stock, (float) ($ingredient->usable_stock ?? 0));
                $reserved = (float) ($ingredient->reserved_quantity ?? 0);
                $needed = $ingredient->rawQuantityForPortion((float) $ingredient->pivot->quantity);

                return $usable - $reserved + 0.0005 < $needed;
            })->pluck('name')->values();
            $isAvailable = $item->isOrderable();

            if (! $isAvailable && $missing->isEmpty()) {
                $missing = collect($item->ingredients->isEmpty()
                    ? ['Recipe not configured']
                    : ['Menu item unavailable']);
            }
            $availability[$item->id] = [
                'available' => $isAvailable,
                'missing' => $missing->map(fn (string $name): string => e($name))->all(),
            ];

            return [
                e($item->name),
                $item->ingredients->map(fn (Ingredient $ingredient): array => [
                    (int) $ingredientIndexes->get($ingredient->id),
                    $ingredient->rawQuantityForPortion((float) $ingredient->pivot->quantity),
                ])->values()->all(),
                (int) $item->id,
            ];
        })->values();

        $stock = $ingredients->map(function (Ingredient $ingredient) use ($today): array {
            $expiry = $ingredient->stockLots
                ->filter(fn ($lot): bool => $lot->expires_at !== null)
                ->map(fn ($lot): int => (int) $today->diffInDays(Carbon::parse($lot->expires_at)->startOfDay(), false))
                ->min();

            return [
                'id' => (int) $ingredient->id,
                'n' => e($ingredient->name),
                's' => $ingredient->usableStock(),
                'm' => (float) $ingredient->min_threshold,
                'u' => e((string) $ingredient->unit),
                'c' => (float) $ingredient->unit_cost,
                'e' => $expiry ?? 999,
                'o' => (bool) $ingredient->manual_override,
            ];
        })->values();

        $logs = InventoryLog::query()
            ->with(['ingredient', 'user', 'order'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (InventoryLog $log): array => [
                'a' => match ($log->type) {
                    'stock_in' => 'Auto-receive',
                    'stock_out' => $log->order_id !== null ? 'Auto-deduct' : 'Stock-out',
                    'waste' => 'Waste-out',
                    default => ucfirst(str_replace('_', '-', (string) $log->type)),
                },
                'q' => abs((float) $log->quantity),
                'u' => e((string) ($log->ingredient?->unit ?? '')),
                'n' => e((string) ($log->ingredient?->name ?? 'Ingredient')),
                't' => $log->created_at?->getTimestamp() * 1000 ?? 0,
                'by' => e((string) ($log->user?->name ?? 'System')),
                'note' => e(implode(' · ', array_filter([
                    $log->order?->reference_number,
                    $log->notes,
                    $log->reason,
                    $log->stage,
                ]))),
            ]);

        return response()->json([
            'ingredients' => $stock,
            'recipes' => $recipes,
            'availability' => $availability,
            'logs' => $logs,
            'near_expiry' => $this->nearExpiryData(),
            'server_now' => now('Asia/Manila')->getTimestamp() * 1000,
        ]);
    }

    public function wasteJson(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['today', 'yesterday', 'week', 'month', 'all'])],
        ]);
        $range = $validated['range'] ?? 'today';
        $query = InventoryLog::query()
            ->with(['ingredient', 'user'])
            ->where('type', 'waste');
        $today = now('Asia/Manila')->startOfDay();

        if ($range !== 'all') {
            [$start, $end] = match ($range) {
                'yesterday' => [$today->copy()->subDay(), $today->copy()->subSecond()],
                'week' => [$today->copy()->startOfWeek(Carbon::MONDAY), $today->copy()->endOfDay()],
                'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
                default => [$today, $today->copy()->endOfDay()],
            };
            $query->whereBetween('created_at', [$start, $end]);
        }

        $entries = $query->latest('created_at')->limit(500)->get()->map(fn (InventoryLog $log): array => [
            't' => $log->created_at?->getTimestamp() * 1000 ?? 0,
            'id' => (int) $log->ingredient_id,
            'n' => e((string) ($log->ingredient?->name ?? 'Ingredient')),
            'q' => abs((float) $log->quantity),
            'u' => e((string) ($log->ingredient?->unit ?? '')),
            'st' => e((string) ($log->stage ?? 'Unspecified')),
            'rs' => e($log->reason === 'Others' && trim((string) $log->notes) !== ''
                ? 'Others: '.$log->notes
                : (string) ($log->reason ?? 'Unspecified')),
            'cost' => $log->cost_amount === null ? null : (float) $log->cost_amount,
            'by' => e((string) ($log->user?->name ?? 'System')),
        ])->values();

        return response()->json([
            'entries' => $entries,
            'near_expiry' => $this->nearExpiryData(),
            'server_now' => now('Asia/Manila')->getTimestamp() * 1000,
        ]);
    }

    public function recordWaste(Request $request, InventoryService $inventory): JsonResponse
    {
        $stages = InventoryTaxonomy::wasteStages();
        $otherReason = InventoryTaxonomy::OTHER_REASON;
        $data = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'unit' => ['required', 'string', 'max:20'],
            'stage' => ['required', Rule::in($stages)],
            'reason' => ['required', Rule::in(InventoryTaxonomy::wasteReasons())],
            'other_reason' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn (): bool => $request->input('reason') === $otherReason)],
        ]);
        $ingredient = Ingredient::query()->findOrFail($data['ingredient_id']);
        $conversions = match ($ingredient->unit) {
            'kg' => ['kg' => 1.0, 'g' => 0.001],
            'g' => ['g' => 1.0, 'kg' => 1000.0],
            'L' => ['L' => 1.0, 'mL' => 0.001],
            'mL' => ['mL' => 1.0, 'L' => 1000.0],
            default => [(string) $ingredient->unit => 1.0],
        };

        if (! array_key_exists($data['unit'], $conversions)) {
            throw ValidationException::withMessages([
                'unit' => "Choose a unit compatible with {$ingredient->name} ({$ingredient->unit}).",
            ]);
        }

        $wholeNumberUnits = ['pcs', 'pack', 'can'];
        if (in_array($data['unit'], $wholeNumberUnits, true) && floor((float) $data['quantity']) !== (float) $data['quantity']) {
            throw ValidationException::withMessages(['quantity' => 'Whole numbers only for '.$data['unit'].'.']);
        }

        $quantity = round((float) $data['quantity'] * $conversions[$data['unit']], 3);
        $reason = $data['reason'];
        $notes = $data['reason'] === $otherReason
            ? trim((string) $data['other_reason'])
            : 'Waste logged in the kitchen.';

        try {
            $cost = DB::transaction(function () use ($inventory, $ingredient, $quantity, $reason, $notes, $data, $request): ?float {
                $cost = $inventory->recordWaste(
                    $ingredient,
                    $quantity,
                    $reason,
                    $notes,
                    $request->user(),
                    $data['stage'],
                );
                ActivityLog::query()->create([
                    'user_id' => $request->user()->id,
                    'action' => 'Kitchen waste recorded',
                    'module' => 'inventory',
                    'subject_type' => Ingredient::class,
                    'subject_id' => $ingredient->id,
                    'metadata' => [
                        'quantity' => (float) $data['quantity'],
                        'unit' => $data['unit'],
                        'reason' => $reason,
                        'other_reason' => $reason === $otherReason ? $notes : null,
                        'stage' => $data['stage'],
                        'cost_amount' => $cost,
                    ],
                ]);

                return $cost;
            }, 3);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Waste logged and stock updated.',
            'cost' => $cost,
            'server_now' => now('Asia/Manila')->getTimestamp() * 1000,
        ], 201);
    }

    /** @return array<int, array{id: int, lot: int, n: string, s: float, u: string, c: float, e: int}> */
    private function nearExpiryData(): array
    {
        $today = now('Asia/Manila')->startOfDay();

        return \App\Models\InventoryStockLot::query()
            ->with('ingredient')
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $today->copy()->addDays(2)->toDateString())
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($lot): array => [
                'id' => (int) $lot->ingredient_id,
                'lot' => (int) $lot->id,
                'n' => e((string) ($lot->ingredient?->name ?? 'Ingredient')),
                's' => (float) $lot->remaining_quantity,
                'u' => e((string) ($lot->ingredient?->unit ?? '')),
                'c' => (float) ($lot->unit_cost ?? $lot->ingredient?->unit_cost ?? 0),
                'e' => (int) $today->diffInDays(Carbon::parse($lot->expires_at)->startOfDay(), false),
                'date' => Carbon::parse($lot->expires_at)->toDateString(),
            ])
            ->all();
    }

    public function board(): View
    {
        return view('staff.kitchen._board', $this->boardData());
    }

    public function inventory(): View
    {
        return view('staff.kitchen._inventory', $this->inventoryData());
    }

    private function pageData(): array
    {
        return $this->boardData() + $this->inventoryData();
    }

    private function boardData(): array
    {
        $orders = Order::query()
            ->notHeld()
            ->with('items')
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->oldest('created_at')
            ->get()
            ->groupBy('status');

        return [
            'pending' => $orders->get('pending', collect()),
            'preparing' => $orders->get('preparing', collect()),
            'ready' => $orders->get('ready', collect()),
        ];
    }

    private function inventoryData(): array
    {
        $ingredients = Ingredient::query()
            ->usedByCurrentMenu()
            ->withUsableStock()
            ->withSum([
                'orderReservations as reserved_quantity' => fn ($query) => $query->where('status', 'reserved'),
            ], 'quantity')
            ->orderBy('name')
            ->get();

        return [
            'ingredients' => $ingredients,
            'lowStockCount' => $ingredients->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock())->count(),
            'menuItems' => MenuItem::query()->withStockAvailability()->orderBy('name')->get(),
            'inventoryLogs' => InventoryLog::query()
                ->with(['ingredient', 'user', 'order'])
                ->latest()
                ->limit(8)
                ->get(),
        ];
    }
}
