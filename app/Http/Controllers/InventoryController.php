<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Services\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(): View
    {
        $ingredients = Ingredient::query()->orderBy('name')->get();
        $logs = InventoryLog::query()
            ->with(['ingredient', 'user'])
            ->latest()
            ->limit(100)
            ->get();
        $lowStockCount = $ingredients->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock())->count();
        $outOfStockCount = $ingredients->filter(fn (Ingredient $ingredient): bool => (float) $ingredient->current_stock <= 0)->count();

        return view('inventory.index', compact('ingredients', 'logs', 'lowStockCount', 'outOfStockCount'));
    }

    public function logs(Request $request): View
    {
        $validated = $request->validate([
            'ingredient' => ['nullable', 'integer', 'exists:ingredients,id'],
        ]);

        $ingredients = Ingredient::query()->orderBy('name')->get();
        $logs = InventoryLog::query()
            ->with(['ingredient', 'user'])
            ->when($validated['ingredient'] ?? null, fn ($query, $ingredientId) => $query->where('ingredient_id', $ingredientId))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('inventory.logs', compact('ingredients', 'logs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['barcode' => trim((string) $request->input('barcode', ''))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:ingredients,name'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:ingredients,barcode'],
            'current_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'unit' => ['required', 'string', 'max:20'],
            'min_threshold' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);
        $validated['barcode'] = $validated['barcode'] !== '' ? $validated['barcode'] : null;

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

    public function assignBarcode(Request $request): RedirectResponse
    {
        $request->merge(['barcode' => trim((string) $request->input('barcode', ''))]);
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'barcode' => ['required', 'string', 'max:64', 'unique:ingredients,barcode'],
        ]);

        $ingredient = Ingredient::query()->findOrFail($validated['ingredient_id']);
        $ingredient->update(['barcode' => $validated['barcode']]);

        return back()->with('success', "Barcode assigned to {$ingredient->name}.");
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        if ($request->has('barcode')) {
            $request->merge(['barcode' => trim((string) $request->input('barcode'))]);
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('ingredients', 'name')->ignore($ingredient->id)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('ingredients', 'barcode')->ignore($ingredient->id)],
            'unit' => ['required', 'string', 'max:20'],
            'min_threshold' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'manual_override' => ['sometimes', 'boolean'],
        ]);

        $validatedBarcode = $request->has('barcode')
            ? ($validated['barcode'] !== '' ? $validated['barcode'] : null)
            : $ingredient->barcode;
        $ingredient->update([
            'name' => $validated['name'],
            'barcode' => $validatedBarcode,
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

    public function log(Request $request, Ingredient $ingredient, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['stock_in', 'stock_out', 'waste', 'adjustment'])],
            'quantity' => [
                'required',
                'numeric',
                $request->input('type') === 'adjustment' ? 'min:0' : 'gt:0',
                'max:999999999',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            if ($validated['type'] === 'adjustment') {
                $inventory->override(
                    $ingredient,
                    (float) $validated['quantity'],
                    $request->user(),
                    $validated['notes'] ?? 'Manual stock adjustment',
                );
            } else {
                $inventory->logMovement(
                    $ingredient,
                    $validated['type'],
                    (float) $validated['quantity'],
                    $request->user(),
                    $validated['notes'] ?? null,
                );
            }
        } catch (\DomainException $exception) {
            return back()->withErrors(['quantity' => $exception->getMessage()]);
        }

        return back()->with('success', match ($validated['type']) {
            'stock_in' => 'Stock receipt recorded.',
            'stock_out' => 'Stock removal recorded.',
            'waste' => 'Waste recorded.',
            'adjustment' => 'Stock count saved.',
        });
    }
}
