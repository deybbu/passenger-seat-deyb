<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Services\RestaurantTableQrService;
use App\Services\TableSessionStatusService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TableSessionController extends Controller
{
    public function index(Request $request, TableSessionStatusService $tableSessionStatuses): View
    {
        abort_unless($request->user()->hasRole('cashier'), 403);

        $sessions = TableSession::query()
            ->with(['orders.items'])
            ->whereNull('closed_at')
            ->get();
        $sessions = $sessions->sortBy('table_number', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $tables = Schema::hasTable('restaurant_tables')
            ? RestaurantTable::query()->get()->sortBy('table_number', SORT_NATURAL | SORT_FLAG_CASE)->values()
            : collect();
        $tableStatuses = $tableSessionStatuses->forTables($tables)->values();

        return view('staff.tables.index', compact('sessions', 'tableStatuses', 'tables'));
    }

    public function posQr(TableSessionStatusService $tableSessionStatuses): View
    {
        $tableSessions = TableSession::query()
            ->whereNull('closed_at')
            ->get(['id', 'table_number', 'public_token']);
        $tableSessions = $tableSessions->sortBy('table_number', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $tables = Schema::hasTable('restaurant_tables')
            ? RestaurantTable::query()->get()->sortBy('table_number', SORT_NATURAL | SORT_FLAG_CASE)->values()
            : collect();
        $tableStatuses = $tableSessionStatuses->forTables($tables)->values();

        $menuItems = MenuItem::query()
            ->with(['category:id,name'])
            ->withStockAvailability()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('menu_category_id')
                ->orWhereHas('category', fn ($category) => $category->where('is_active', true)))
            ->orderBy('name')
            ->get()
            ->map(function (MenuItem $item): array {
                $ingredients = $item->ingredients;
                $availableStock = $ingredients->isEmpty()
                    ? null
                    : (int) $ingredients->map(
                        fn (Ingredient $ingredient): int => (int) floor(
                            max(0, (float) $ingredient->current_stock - (float) ($ingredient->reserved_quantity ?? 0))
                            / (float) $ingredient->pivot->quantity
                            + 0.0005
                        )
                    )->min();

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category?->name ?? 'Uncategorized',
                    'price' => (float) $item->price,
                    'stock' => $item->is_available ? $availableStock : 0,
                    'available' => $item->isOrderable(),
                    'photo' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
                ];
            });

        return view('pos.table-qr', [
            'tableSessions' => $tableSessions,
            'tableStatuses' => $tableStatuses,
            'menuItems' => $menuItems,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function toggleQr(
        Request $request,
        RestaurantTable $restaurantTable,
        RestaurantTableQrService $qr,
    ): RedirectResponse {
        abort_unless($request->user()->hasRole('cashier') || $request->user()->hasRole('admin'), 403);
        if (! $qr->isGloballyEnabled()) {
            return redirect()->route('staff.pos.table-qr')
                ->with('error', 'Customer QR menus are globally disabled in System Settings.');
        }

        $table = $qr->toggle($restaurantTable, $request->user());

        return redirect()->route('staff.pos.table-qr')->with('status', $table->is_enabled
            ? "{$table->name} QR ordering enabled."
            : "{$table->name} QR ordering disabled.");
    }

    public function open(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('cashier'), 403);

        $data = $request->validate([
            'table_number' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9 -]+$/', 'exists:restaurant_tables,table_number'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:30'],
        ]);
        $table = trim($data['table_number']);
        $restaurantTable = RestaurantTable::query()->where('table_number', $table)->firstOrFail();
        abort_if($table === '', 422, 'Enter a table number.');

        try {
            $session = DB::transaction(function () use ($request, $table, $data, $restaurantTable): TableSession {
                return TableSession::create([
                    'table_number' => $table,
                    'table_id' => $restaurantTable->id,
                    'public_token' => Str::random(64),
                    'active_table_number' => $table,
                    'guest_count' => $data['guest_count'],
                    'opened_by' => $request->user()->id,
                    'opened_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! TableSession::query()->where('active_table_number', $table)->exists()) {
                throw $exception;
            }

            return back()->with('error', "Table {$table} already has an open QR session.");
        }

        return back()->with('status', "Table {$table} is open. QR link: " . route('customer.qr.show', ['table' => $session->table_number]));
    }

    public function requestBill(Request $request, TableSession $tableSession): RedirectResponse
    {
        abort_unless($request->user()->hasRole('cashier'), 403);
        abort_if($tableSession->closed_at !== null, 409, 'This table session is already closed.');
        $tableSession->update([
            'status' => 'bill_requested',
            'bill_requested_at' => now(),
        ]);

        return back()->with('status', "Bill requested for table {$tableSession->table_number}.");
    }

    public function close(Request $request, TableSession $tableSession): RedirectResponse
    {
        abort_unless($request->user()->hasRole('cashier'), 403);

        return DB::transaction(function () use ($request, $tableSession): RedirectResponse {
            $session = TableSession::query()->lockForUpdate()->findOrFail($tableSession->id);
            abort_if($session->closed_at !== null, 409, 'This table session is already closed.');

            $orders = Order::query()->where('table_session_id', $session->id)->get();
            $hasUnfinished = $orders->contains(fn (Order $order): bool => in_array($order->status, [
                'pending', 'preparing', 'ready',
            ], true));
            $hasUnpaid = $orders->contains(fn (Order $order): bool => $order->payment_status !== 'paid'
                && $order->status !== 'cancelled');

            if ($hasUnfinished || $hasUnpaid) {
                return back()->with('error', 'Complete and record payment for every order before closing this table.');
            }

            $session->update([
                'closed_at' => now(),
                'active_table_number' => null,
                'closed_by' => $request->user()->id,
            ]);

            return back()->with('status', "Table {$session->table_number} QR access is closed.");
        });
    }
}
