<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableSession;
use App\Services\OrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerQrOrderController extends Controller
{
    public function show(Request $request): View
    {
        $tableNumber = trim($request->query('table', ''));
        $tableSession = null;
        $invalidTable = $tableNumber === '' || ! preg_match('/^[A-Za-z0-9 -]{1,10}$/', $tableNumber);
        $tableOpen = false;

        if (! $invalidTable) {
            $tableSession = TableSession::query()
                ->where('table_number', $tableNumber)
                ->whereNull('closed_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest('opened_at')
                ->first();
            $tableOpen = $tableSession !== null;
        }

        $items = $tableOpen
            ? MenuItem::query()
                ->with('category:id,name')
                ->withStockAvailability()
                ->where('is_active', true)
                ->where('is_available', true)
                ->where(fn ($query) => $query
                    ->whereDoesntHave('category')
                    ->orWhereHas('category', fn ($category) => $category->where('is_active', true)))
                ->orderBy('name')
                ->get()
                ->filter(fn (MenuItem $item): bool => $item->isOrderable())
                ->map(fn (MenuItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => (float) $item->price,
                    'category' => $item->category?->name ?? 'Uncategorized',
                    'ingredients' => $item->ingredients->pluck('name')->values(),
                    'photo' => $item->image_path
                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->image_path)
                        : null,
                ])
            : collect();

        return view('customer.qr-order', [
            'tableNumber' => $tableNumber,
            'invalidTable' => $invalidTable,
            'tableOpen' => $tableOpen,
            'tableToken' => $tableSession?->public_token,
            'submissionToken' => (string) Str::uuid(),
            'items' => $items,
            'categories' => $items->pluck('category')->unique()->values(),
        ]);
    }

    public function place(Request $request, OrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'table_token' => ['required', 'string', 'size:64'],
            'submission_token' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'min:1', 'max:20'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = Order::query()
            ->where('submission_token', $validated['submission_token'])
            ->first();

        if ($existing !== null) {
            return response()->json([
                'redirect' => route('customer.qr.show', ['table' => $existing->table_number]),
                'reference' => $existing->reference_number,
            ]);
        }

        $tableSession = TableSession::query()
            ->where('public_token', $validated['table_token'])
            ->whereNull('closed_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($tableSession === null) {
            return response()->json([
                'message' => 'This table session has ended. Please ask staff for help.',
            ], 409);
        }

        $quantities = collect($validated['items']);
        $items = MenuItem::query()
            ->withStockAvailability()
            ->whereIn('id', $quantities->keys())
            ->where('is_active', true)
            ->where('is_available', true)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($items->count() !== $quantities->count() || $items->contains(fn (MenuItem $item): bool => ! $item->isOrderable())) {
            throw ValidationException::withMessages([
                'items' => 'One or more selected dishes are no longer available. Refresh the menu and try again.',
            ]);
        }

        $lines = $quantities->map(function ($quantity, $id) use ($items) {
            $item = $items->get((int) $id);

            return (object) [
                'item' => $item,
                'qty' => (int) $quantity,
                'line_total' => round((float) $item->price * (int) $quantity, 2),
            ];
        })->values();

        try {
            $order = $orders->place([
                'type' => 'dine_in',
                'table_number' => $tableSession->table_number,
                'table_session_id' => $tableSession->id,
                'submission_token' => $validated['submission_token'],
                'customer_name' => 'Table guest',
                'customer_phone' => null,
                'special_instructions' => $validated['special_instructions'] ?? null,
            ], $lines);
        } catch (UniqueConstraintViolationException $exception) {
            $order = Order::query()
                ->where('submission_token', $validated['submission_token'])
                ->first();

            if ($order === null) {
                throw $exception;
            }
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'redirect' => route('customer.qr.show', ['table' => $order->table_number]),
            'reference' => $order->reference_number,
        ], 201);
    }

    public function orders(TableSession $tableSession): JsonResponse
    {
        $orders = $tableSession->orders()
            ->with('items')
            ->latest()
            ->get()
            ->map(fn (Order $order): array => [
                'reference' => $order->reference_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total_amount,
                'special_instructions' => $order->special_instructions,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item): array => [
                    'name' => $item->item_name,
                    'quantity' => (int) $item->quantity,
                ])->all(),
            ]);

        return response()->json([
            'orders' => $orders,
            'bill_requested' => $tableSession->bill_requested_at !== null,
        ]);
    }

    public function requestBill(TableSession $tableSession): JsonResponse
    {
        $updated = DB::table('table_sessions')
            ->where('id', $tableSession->id)
            ->whereNull('closed_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update(['bill_requested_at' => now(), 'updated_at' => now()]);

        if ($updated === 0) {
            return response()->json(['message' => 'This table session has ended. Please ask staff for help.'], 409);
        }

        return response()->json(['message' => 'Bill request sent. Staff will be with you shortly.']);
    }
}
