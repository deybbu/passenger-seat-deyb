<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Contracts\View\View;

class KitchenController extends Controller
{
    public function index(): View
    {
        return view('staff.kitchen.index', $this->pageData());
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
