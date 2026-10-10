<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(): View
    {
        $ingredients = Ingredient::query()->with('logs.user')->orderBy('name')->paginate(30);
        $lowStockCount = Ingredient::query()
            ->where('manual_override', false)
            ->whereColumn('current_stock', '<=', 'min_threshold')
            ->count();

        return view('inventory.index', compact('ingredients', 'lowStockCount'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:ingredients,name'],
            'current_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'unit' => ['required', 'string', 'max:20'],
            'min_threshold' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $ingredient = Ingredient::create($validated);
            if ((float) $validated['current_stock'] > 0) {
                $ingredient->logs()->create([
                    'user_id' => $request->user()->id,
                    'type' => 'stock_in',
                    'quantity' => $validated['current_stock'],
                    'stock_before' => 0,
                    'stock_after' => $validated['current_stock'],
                    'notes' => 'Opening stock',
                ]);
            }
        });

        return back()->with('success', 'Ingredient created.');
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('ingredients', 'name')->ignore($ingredient->id)],
            'unit' => ['required', 'string', 'max:20'],
            'min_threshold' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'manual_override' => ['sometimes', 'boolean'],
        ]);

        $ingredient->update([
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'min_threshold' => $validated['min_threshold'],
            'manual_override' => $request->boolean('manual_override'),
        ]);

        return back()->with('success', 'Ingredient updated.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        abort_if($ingredient->logs()->exists(), 409, 'Ingredients with stock history cannot be deleted. Rename or archive them instead.');
        $ingredient->delete();

        return back()->with('success', 'Ingredient deleted.');
    }

    public function log(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['stock_in', 'stock_out', 'adjustment'])],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($ingredient, $validated, $request): void {
            $locked = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);
            $before = (float) $locked->current_stock;
            $quantity = (float) $validated['quantity'];

            $after = match ($validated['type']) {
                'stock_in' => $before + $quantity,
                'stock_out' => $before - $quantity,
                'adjustment' => $quantity,
            };

            abort_if($after < 0, 422, 'Stock cannot be reduced below zero.');

            $locked->update(['current_stock' => $after]);
            InventoryLog::create([
                'ingredient_id' => $locked->id,
                'user_id' => $request->user()->id,
                'type' => $validated['type'],
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'notes' => $validated['notes'] ?? null,
            ]);
        }, 3);

        return back()->with('success', 'Stock log recorded.');
    }
}
