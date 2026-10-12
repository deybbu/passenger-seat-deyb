<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TableSessionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('cashier'), 403);

        $sessions = TableSession::query()
            ->with(['orders.items'])
            ->whereNull('closed_at')
            ->orderBy('table_number')
            ->get();

        return view('staff.tables.index', compact('sessions'));
    }

    public function posQr(): View
    {
        $tableSessions = TableSession::query()
            ->whereNull('closed_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('table_number')
            ->get(['id', 'table_number', 'public_token']);

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
            'menuItems' => $menuItems,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('cashier'), 403);

        $data = $request->validate([
            'table_number' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9 -]+$/'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:30'],
        ]);
        $table = trim($data['table_number']);
        abort_if($table === '', 422, 'Enter a table number.');

        try {
            $session = DB::transaction(function () use ($request, $table, $data): TableSession {
                TableSession::query()
                    ->where('table_number', $table)
                    ->whereNull('closed_at')
                    ->where('expires_at', '<=', now())
                    ->update([
                        'closed_at' => now(),
                        'active_table_number' => null,
                        'closed_by' => $request->user()->id,
                    ]);

                return TableSession::create([
                    'table_number' => $table,
                    'public_token' => Str::random(64),
                    'active_table_number' => $table,
                    'guest_count' => $data['guest_count'],
                    'opened_by' => $request->user()->id,
                    'opened_at' => now(),
                    'expires_at' => now()->addMinutes(180),
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

    public function extend(Request $request, TableSession $tableSession): RedirectResponse
    {
        abort_unless($request->user()->hasRole('cashier'), 403);
        $session = DB::transaction(function () use ($tableSession): TableSession {
            $locked = TableSession::query()->lockForUpdate()->findOrFail($tableSession->id);
            abort_if($locked->closed_at !== null, 409, 'This table session is already closed.');

            $base = $locked->expires_at && $locked->expires_at->isFuture()
                ? $locked->expires_at
                : now();
            $locked->update(['expires_at' => $base->addMinutes(30)]);

            return $locked;
        });

        return back()->with('status', "Table {$session->table_number} QR access was extended by 30 minutes.");
    }

    public function requestBill(Request $request, TableSession $tableSession): RedirectResponse
    {
        abort_unless($request->user()->hasRole('cashier'), 403);
        abort_if($tableSession->closed_at !== null, 409, 'This table session is already closed.');
        $tableSession->update(['bill_requested_at' => now()]);

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
