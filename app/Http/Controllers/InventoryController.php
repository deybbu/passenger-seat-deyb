<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\InventoryPackage;
use App\Models\InventoryStockLot;
use App\Models\MenuItem;
use App\Models\PreparationRecipe;
use App\Services\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    private const STORAGE_ZONES = ['Walk-in Freezer', 'Chiller', 'Dry Pantry Room'];

    public function index(): View
    {
        $isManager = auth()->user()->hasRole('admin');
        $ingredients = Ingredient::query()
            ->with(['stockLots' => function ($query): void {
                $query->with('package')
                    ->where('remaining_quantity', '>', 0)
                    ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('expires_at')
                    ->orderBy('id');
            }])
            ->orderBy('name')
            ->get();
        $today = today()->toDateString();
        foreach ($ingredients as $ingredient) {
            $usableStock = $ingredient->stockLots
                ->filter(fn (InventoryStockLot $lot): bool => $lot->expires_at === null || $lot->expires_at->toDateString() >= $today)
                ->sum('remaining_quantity');
            $ingredient->setAttribute(
                'usable_stock',
                round(min((float) $ingredient->current_stock, (float) $usableStock), 3),
            );
        }
        $packages = InventoryPackage::query()
            ->with('ingredient')
            ->where('is_active', true)
            ->orderBy('label')
            ->limit(8)
            ->get();
        $lowStockCount = $ingredients->filter(fn (Ingredient $ingredient): bool => $ingredient->isLowStock())->count();
        $preparationRecipes = PreparationRecipe::query()
            ->with(['outputIngredient', 'lines.ingredient'])
            ->orderBy('output_ingredient_id')
            ->get();
        $recipeMatrix = $isManager
            ? Ingredient::query()->with('menuItems')->orderBy('name')->get()
            : collect();
        $menuRecipeCosts = $isManager
            ? MenuItem::query()->with('ingredients')->orderBy('name')->get()->map(function (MenuItem $menuItem): array {
                $ingredients = $menuItem->ingredients;
                $hasRecipe = $ingredients->isNotEmpty();
                $hasAllCosts = $hasRecipe && $ingredients->every(
                    fn (Ingredient $ingredient): bool => $ingredient->unit_cost !== null,
                );
                $estimatedCost = $hasAllCosts
                    ? $ingredients->sum(fn (Ingredient $ingredient): float => $ingredient->rawQuantityForPortion(
                        (float) $ingredient->pivot->quantity,
                    ) * (float) $ingredient->unit_cost)
                    : null;

                return [
                    'item' => $menuItem,
                    'has_recipe' => $hasRecipe,
                    'estimated_cost' => $estimatedCost,
                ];
            })
            : collect();
        $movementHistory = InventoryLog::query()
            ->with(['ingredient', 'user', 'stockLot'])
            ->latest()
            ->paginate(50)
            ->withQueryString();
        $lossReport = $isManager
            ? InventoryLog::query()
                ->with(['ingredient', 'user'])
                ->where('type', 'waste')
                ->latest()
                ->limit(100)
                ->get()
            : collect();
        $knownWasteCost = $isManager
            ? (float) InventoryLog::query()->where('type', 'waste')->whereNotNull('cost_amount')->sum('cost_amount')
            : null;
        $uncostedWasteCount = $isManager
            ? InventoryLog::query()->where('type', 'waste')->whereNull('cost_amount')->count()
            : 0;

        $freshReceiptToken = (string) Str::uuid();

        return view('inventory.index', compact(
            'ingredients',
            'packages',
            'lowStockCount',
            'freshReceiptToken',
            'preparationRecipes',
            'recipeMatrix',
            'menuRecipeCosts',
            'movementHistory',
            'isManager',
            'lossReport',
            'knownWasteCost',
            'uncostedWasteCount',
        ));
    }

    public function updateRecipeSettings(Request $request, Ingredient $ingredient): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'unit_cost' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'yield_loss_percent' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'min_threshold' => ['required', 'numeric', 'min:0', 'max:9999999.999'],
        ]);

        $ingredient->update($validated);

        return redirect()
            ->route('inventory.index', ['tab' => 'rcp'])
            ->with('success', "Recipe cost and par settings saved for {$ingredient->name}.");
    }

    public function storePreparationRecipe(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'output_ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'yield_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.ingredient_id' => ['required', 'integer', 'distinct', 'exists:ingredients,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        if (collect($validated['lines'])->contains(
            fn (array $line): bool => (int) $line['ingredient_id'] === (int) $validated['output_ingredient_id'],
        )) {
            return back()->withErrors([
                'lines' => 'A prepared item cannot also be one of its own raw ingredients.',
            ])->withInput();
        }

        DB::transaction(function () use ($validated): void {
            $recipe = PreparationRecipe::query()->updateOrCreate(
                ['output_ingredient_id' => $validated['output_ingredient_id']],
                ['yield_quantity' => $validated['yield_quantity']],
            );
            $recipe->lines()->delete();
            foreach ($validated['lines'] as $line) {
                $recipe->lines()->create($line);
            }
        });

        return redirect()
            ->route('inventory.index', ['tab' => 'prep'])
            ->with('success', 'Preparation recipe saved.');
    }

    public function recordPreparation(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'preparation_recipe_id' => ['required', 'integer', 'exists:preparation_recipes,id'],
            'batches' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ]);

        try {
            $prepared = $inventory->prepare(
                PreparationRecipe::query()->findOrFail($validated['preparation_recipe_id']),
                (float) $validated['batches'],
                $request->user(),
            );
        } catch (\DomainException $exception) {
            return redirect()
                ->route('inventory.index', ['tab' => 'prep'])
                ->withErrors(['batches' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.index', ['tab' => 'prep'])
            ->with('success', "Prepared {$prepared['output_quantity']} {$prepared['output_ingredient']->unit} of {$prepared['output_ingredient']->name}.");
    }

    public function recordWaste(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reason' => ['required', Rule::in(['Pre-Cook Spoilage', 'Over-Prep Waste', 'Boodle Return', 'Other'])],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        try {
            $estimatedCost = $inventory->recordWaste(
                Ingredient::query()->findOrFail($validated['ingredient_id']),
                (float) $validated['quantity'],
                $validated['reason'],
                trim($validated['notes']),
                $request->user(),
            );
        } catch (\DomainException $exception) {
            return redirect()
                ->route('inventory.index', ['tab' => 'prep'])
                ->withErrors(['quantity' => $exception->getMessage()])
                ->withInput();
        }

        $message = 'Waste recorded.';
        if ($estimatedCost !== null) {
            $message .= ' Estimated cost: ₱'.number_format($estimatedCost, 2).'.';
        }

        return redirect()
            ->route('inventory.index', ['tab' => 'prep'])
            ->with('success', $message)
            ->with('waste_estimate', $estimatedCost);
    }

    public function receiveFresh(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(['Seafood', 'Produce', 'Meat', 'Other'])],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'unit' => ['required', Rule::in(['kg', 'pc'])],
            'batch_number' => ['required', 'string', 'max:120'],
            'expires_at' => ['required', 'date'],
            'storage_zone' => ['required', Rule::in(self::STORAGE_ZONES)],
            'receipt_token' => ['required', 'uuid'],
        ]);

        try {
            $receipt = $inventory->receiveFresh(
                trim($validated['name']),
                $validated['category'],
                (float) $validated['quantity'],
                $validated['unit'],
                trim($validated['batch_number']),
                $validated['expires_at'],
                $validated['storage_zone'],
                $validated['receipt_token'],
                $request->user(),
            );
        } catch (\DomainException $exception) {
            $redirect = redirect()
                ->route('inventory.index', ['tab' => 'fresh'])
                ->withErrors(['fresh' => $exception->getMessage()]);

            return $exception->getCode() === 409 ? $redirect : $redirect->withInput();
        }

        return redirect()
            ->route('inventory.index', ['tab' => 'dash'])
            ->with('success', "Received {$receipt['quantity']} {$receipt['ingredient']->unit} of {$receipt['ingredient']->name}.");
    }

    public function lookupBarcode(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:64'],
        ]);
        $barcode = trim($validated['barcode']);
        $resolved = $inventory->resolveBarcode($barcode);

        if ($resolved === null) {
            return response()->json([
                'message' => 'This barcode is not registered to an active package or ingredient.',
                'barcode' => $barcode,
                'can_register' => true,
            ], 404);
        }

        $ingredient = $resolved['ingredient'];
        $package = $resolved['package'];
        $quantity = $package === null ? null : (float) $package->package_quantity;
        $current = round((float) $ingredient->current_stock, 3);

        return response()->json([
            'barcode' => $barcode,
            'ingredient' => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'unit' => $ingredient->unit,
            ],
            'package' => $package === null ? null : [
                'id' => $package->id,
                'label' => $package->label,
                'quantity' => $quantity,
            ],
            'current_stock' => $current,
            'package_quantity' => $quantity,
            'expected_stock' => $quantity === null ? null : round($current + $quantity, 3),
        ]);
    }

    public function previewReceipt(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:64'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        try {
            $preview = $inventory->previewReceipt(
                trim($validated['barcode']),
                isset($validated['quantity']) ? (float) $validated['quantity'] : null,
            );
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'current_stock' => $preview['current_stock'],
            'package_quantity' => $preview['package_quantity'],
            'expected_stock' => $preview['expected_stock'],
            'unit' => $preview['ingredient']->unit,
        ]);
    }

    public function confirmReceipt(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:64'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'receipt_token' => ['required', 'uuid'],
            'batch_number' => ['required', 'string', 'max:120'],
            'expires_at' => ['required', 'date'],
            'storage_zone' => ['required', Rule::in(self::STORAGE_ZONES)],
        ]);

        try {
            $receipt = $inventory->receivePackage(
                trim($validated['barcode']),
                isset($validated['quantity']) ? (float) $validated['quantity'] : null,
                $validated['receipt_token'],
                trim($validated['batch_number']),
                $validated['expires_at'],
                $validated['storage_zone'],
                $request->user(),
            );
        } catch (\DomainException $exception) {
            $status = $exception->getCode() === 409 ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        } catch (QueryException $exception) {
            if (! InventoryStockLot::query()->where('receipt_token', $validated['receipt_token'])->exists()) {
                throw $exception;
            }

            return response()->json([
                'message' => 'This receipt was already confirmed. Scan the package again for a new delivery.',
            ], 409);
        }

        return response()->json([
            'message' => 'Receipt confirmed. Stock and the receiving lot have been recorded.',
            'ingredient' => $receipt['ingredient']->name,
            'current_stock' => $receipt['current_stock'],
            'package_quantity' => $receipt['package_quantity'],
            'expected_stock' => $receipt['expected_stock'],
            'unit' => $receipt['ingredient']->unit,
        ], 201);
    }

    public function registerPackage(Request $request): JsonResponse
    {
        $request->merge(['barcode' => trim((string) $request->input('barcode', ''))]);
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'barcode' => ['required', 'string', 'max:64', 'unique:inventory_packages,barcode'],
            'label' => ['required', 'string', 'max:120'],
            'package_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        if (Ingredient::query()->where('barcode', $validated['barcode'])->exists()) {
            return response()->json(['message' => 'This barcode is already assigned to an ingredient.'], 422);
        }

        $package = InventoryPackage::create([
            ...$validated,
            'is_active' => true,
        ]);
        $package->load('ingredient');

        return response()->json([
            'message' => 'Package barcode registered.',
            'barcode' => $package->barcode,
            'ingredient' => [
                'id' => $package->ingredient->id,
                'name' => $package->ingredient->name,
                'unit' => $package->ingredient->unit,
            ],
            'package' => [
                'id' => $package->id,
                'label' => $package->label,
                'quantity' => (float) $package->package_quantity,
            ],
            'current_stock' => round((float) $package->ingredient->current_stock, 3),
            'package_quantity' => (float) $package->package_quantity,
            'expected_stock' => round(
                (float) $package->ingredient->current_stock + (float) $package->package_quantity,
                3,
            ),
        ], 201);
    }

    public function logs(Request $request): View
    {
        $validated = $request->validate([
            'ingredient' => ['nullable', 'integer', 'exists:ingredients,id'],
        ]);

        $ingredients = Ingredient::query()->orderBy('name')->get();
        $logs = InventoryLog::query()
            ->with(['ingredient', 'user', 'stockLot'])
            ->when($validated['ingredient'] ?? null, fn ($query, $ingredientId) => $query->where('ingredient_id', $ingredientId))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('inventory.logs', compact('ingredients', 'logs'));
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $request->merge(['barcode' => trim((string) $request->input('barcode', ''))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:ingredients,name'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:ingredients,barcode', 'unique:inventory_packages,barcode'],
            'current_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'unit' => ['required', 'string', 'max:20'],
            'min_threshold' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);
        $validated['barcode'] = $validated['barcode'] !== '' ? $validated['barcode'] : null;

        DB::transaction(function () use ($validated, $request, $inventory): void {
            $initialStock = (float) $validated['current_stock'];
            $ingredientData = $validated;
            $ingredientData['current_stock'] = 0;
            $ingredient = Ingredient::create($ingredientData);

            if ($initialStock > 0) {
                $inventory->logMovement(
                    $ingredient,
                    'stock_in',
                    $initialStock,
                    $request->user(),
                    'Opening stock',
                );
            }
        });

        return back()->with('success', 'Ingredient created.');
    }

    public function assignBarcode(Request $request): RedirectResponse
    {
        $request->merge(['barcode' => trim((string) $request->input('barcode', ''))]);
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'barcode' => ['required', 'string', 'max:64', 'unique:ingredients,barcode', 'unique:inventory_packages,barcode'],
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
            'barcode' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('ingredients', 'barcode')->ignore($ingredient->id),
                Rule::unique('inventory_packages', 'barcode'),
            ],
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
            'type' => ['required', Rule::in(['stock_in', 'stock_out', 'adjustment'])],
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
            'adjustment' => 'Stock count saved.',
        });
    }
}
