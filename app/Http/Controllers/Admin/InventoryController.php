<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\InventoryStockLot;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\InventoryService;
use App\Support\InventoryTaxonomy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(): View
    {
        $ingredients = Ingredient::query()
            ->usedByCurrentMenu()
            ->with(['stockLots' => fn ($query) => $query
                ->where('remaining_quantity', '>', 0)
                ->orderBy('received_at')
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('id')])
            ->orderBy('name')
            ->get();
        $consumption = InventoryLog::query()
            ->selectRaw('ingredient_id, SUM(ABS(quantity)) as used_quantity')
            ->whereIn('type', ['stock_out', 'prep_out', 'waste'])
            ->where('created_at', '>=', today()->subDays(29)->startOfDay())
            ->groupBy('ingredient_id')
            ->pluck('used_quantity', 'ingredient_id');
        $lowStockCount = 0;
        $outOfStockCount = 0;
        $allPricesKnown = $ingredients->isNotEmpty();
        $stockValue = 0.0;

        foreach ($ingredients as $ingredient) {
            $validLots = $ingredient->stockLots
                ->filter(fn (InventoryStockLot $lot): bool => $lot->expires_at === null || $lot->expires_at->toDateString() >= today()->toDateString());
            $hasLots = $ingredient->stockLots->isNotEmpty();
            $usableStock = $hasLots
                ? min((float) $ingredient->current_stock, (float) $validLots->sum('remaining_quantity'))
                : (float) $ingredient->current_stock;
            $usableStock = round($usableStock, 3);
            $dailyUsage = ((float) ($consumption->get($ingredient->id) ?? 0)) / 30;
            $daysLeft = $dailyUsage > 0 ? $usableStock / $dailyUsage : null;
            $nextLot = $ingredient->stockLots->first();
            $isOut = $usableStock <= 0;
            $isLow = ! $isOut && ! $ingredient->manual_override && $usableStock <= (float) $ingredient->min_threshold;

            $ingredient->setAttribute('admin_usable_stock', $usableStock);
            $ingredient->setAttribute('admin_days_left', $daysLeft);
            $ingredient->setAttribute('admin_next_expiry', $nextLot?->expires_at);
            $ingredient->setAttribute('admin_storage_zone', $nextLot?->storage_zone);
            $ingredient->setAttribute('admin_stock_status', $isOut ? 'Out' : ($isLow ? 'Low' : 'OK'));

            if ($isOut) {
                $outOfStockCount++;
            } elseif ($isLow) {
                $lowStockCount++;
            }

            if ($ingredient->unit_cost === null) {
                $allPricesKnown = false;
            } else {
                $stockValue += $usableStock * (float) $ingredient->unit_cost;
            }
        }

        $batches = InventoryStockLot::query()
            ->with('ingredient')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();
        $firstUsableLotIds = [];
        foreach ($batches as $lot) {
            if ($lot->expires_at !== null && $lot->expires_at->toDateString() < today()->toDateString()) {
                continue;
            }
            $firstUsableLotIds[$lot->ingredient_id] ??= $lot->id;
        }

        return view('admin.inventory', [
            'ingredients' => $ingredients,
            'batches' => $batches,
            'firstUsableLotIds' => $firstUsableLotIds,
            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
            'stockValue' => $allPricesKnown ? $stockValue : null,
        ]);
    }

    public function storeIngredient(Request $request, InventoryService $inventory): JsonResponse|RedirectResponse
    {
        $request->merge(['barcode' => trim((string) $request->input('barcode', ''))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:ingredients,name'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:ingredients,barcode', 'unique:inventory_packages,barcode'],
            'current_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'unit' => ['required', 'string', 'max:20'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'min_threshold' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);
        $validated['barcode'] = $validated['barcode'] !== '' ? $validated['barcode'] : null;

        $ingredient = DB::transaction(function () use ($validated, $request, $inventory): Ingredient {
            $openingStock = (float) $validated['current_stock'];
            $ingredient = Ingredient::create([
                ...$validated,
                'current_stock' => 0,
            ]);

            if ($openingStock > 0) {
                $inventory->logMovement($ingredient, 'stock_in', $openingStock, $request->user(), 'Opening stock');
            }

            return $ingredient;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Ingredient created.',
                'ingredient' => $ingredient->fresh(),
            ], 201);
        }

        return redirect()->route('admin.inventory')->with('status', 'Ingredient created.');
    }

    public function storePurchaseOrder(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_name' => ['required', 'string', 'max:150'],
        ]);

        $purchaseOrder = DB::transaction(function () use ($request, $ingredient, $validated): PurchaseOrder {
            $lockedIngredient = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);
            $usableStock = $lockedIngredient->usableStock();
            $minimum = (float) $lockedIngredient->min_threshold;
            $quantity = (int) ceil(max(($minimum * 2) - $usableStock, $minimum));

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'supplier_name' => 'Set a positive minimum stock threshold before generating a purchase order.',
                ]);
            }

            $reference = 'PO-'.Str::ulid();
            $purchaseOrder = PurchaseOrder::query()->create([
                'reference' => $reference,
                'supplier_name' => trim($validated['supplier_name']),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $purchaseOrder->items()->create([
                'ingredient_id' => $lockedIngredient->id,
                'ordered_quantity' => $quantity,
                'received_quantity' => 0,
                'expected_unit_cost' => $lockedIngredient->unit_cost,
            ]);
            $this->recordActivity(
                $request,
                'Inventory',
                "Generated {$purchaseOrder->reference} for {$lockedIngredient->name}",
                $purchaseOrder,
                ['ingredient_id' => $lockedIngredient->id, 'quantity' => $quantity],
            );

            return $purchaseOrder;
        });

        return redirect()->route('admin.purchase-orders')
            ->with('status', "{$purchaseOrder->reference} draft created for {$ingredient->name}; manager approval is required.");
    }

    public function referencePurchaseOrder(Request $request, Ingredient $ingredient): JsonResponse
    {
        $validated = $request->validate(['supplier_name' => ['required', 'string', 'max:150']]);
        $purchaseOrder = DB::transaction(function () use ($request, $ingredient, $validated): PurchaseOrder {
            $lockedIngredient = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);
            $quantity = (int) ceil(max(
                ((float) $lockedIngredient->min_threshold * 2) - $lockedIngredient->usableStock(),
                (float) $lockedIngredient->min_threshold,
            ));
            if ($quantity < 1) {
                throw ValidationException::withMessages(['supplier_name' => 'Set a positive minimum stock threshold first.']);
            }
            $order = PurchaseOrder::query()->create([
                'reference' => 'PO-'.Str::ulid(),
                'supplier_name' => trim($validated['supplier_name']),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $order->items()->create([
                'ingredient_id' => $lockedIngredient->id,
                'ordered_quantity' => $quantity,
                'received_quantity' => 0,
                'expected_unit_cost' => $lockedIngredient->unit_cost,
            ]);
            return $order;
        });

        return response()->json(['id' => $purchaseOrder->id, 'reference' => $purchaseOrder->reference, 'status' => $purchaseOrder->status]);
    }

    public function referenceCloseShort(Request $request, PurchaseOrderItem $purchaseOrderItem): JsonResponse
    {
        $message = DB::transaction(function () use ($purchaseOrderItem): string {
            $item = PurchaseOrderItem::query()->lockForUpdate()->findOrFail($purchaseOrderItem->id);
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($item->purchase_order_id);
            $remaining = round((float) $item->ordered_quantity - (float) $item->received_quantity, 3);
            if (! in_array($order->status, ['open', 'partial'], true) || $order->closed_at !== null
                || (float) $item->received_quantity <= 0 || $remaining < 0.001) {
                throw ValidationException::withMessages(['purchase_order' => 'This purchase order cannot be closed short.']);
            }
            $ingredient = Ingredient::query()->findOrFail($item->ingredient_id);
            $credit = $item->expected_unit_cost === null ? null : round($remaining * (float) $item->expected_unit_cost, 2);
            $order->update(['status' => 'closed_short', 'closed_at' => now()]);
            return $credit === null ? 'Short delivery closed.' : 'Credit memo drafted: ₱'.number_format($credit, 2);
        });

        return response()->json(['message' => $message]);
    }

    public function referenceApprovePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'draft' || $purchaseOrder->items()->doesntExist()) {
            return response()->json(['message' => 'Only a non-empty draft purchase order can be approved.'], 409);
        }
        $purchaseOrder->update([
            'status' => 'open', 'approved_by' => $request->user()->id,
            'approved_at' => now(), 'ordered_at' => now(),
        ]);
        return response()->json(['message' => 'Purchase order approved and open for receiving.']);
    }

    public function referenceWaste(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'stage' => ['required', Rule::in(InventoryTaxonomy::wasteStages())],
            'reason' => ['required', Rule::in(InventoryTaxonomy::wasteReasons())],
            'other_reason' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn (): bool => $request->input('reason') === InventoryTaxonomy::OTHER_REASON)],
        ]);
        $reason = $validated['reason'] === InventoryTaxonomy::OTHER_REASON
            ? 'Others: '.trim((string) $validated['other_reason'])
            : $validated['reason'];
        $cost = $inventory->recordWaste(
            Ingredient::findOrFail($validated['ingredient_id']),
            (float) $validated['quantity'],
            $reason,
            $reason,
            $request->user(),
            $validated['stage'],
        );

        return response()->json(['cost' => $cost]);
    }

    public function purchaseOrders(): View
    {
        $purchaseOrders = $this->purchaseOrdersWithItems();

        return view('admin.purchase-orders', compact('purchaseOrders'));
    }

    public function closeShort(Request $request, PurchaseOrderItem $purchaseOrderItem): RedirectResponse
    {
        $message = DB::transaction(function () use ($request, $purchaseOrderItem): string {
            $item = PurchaseOrderItem::query()->lockForUpdate()->findOrFail($purchaseOrderItem->id);
            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($item->purchase_order_id);
            $remaining = round((float) $item->ordered_quantity - (float) $item->received_quantity, 3);

            if (! in_array($purchaseOrder->status, ['open', 'partial'], true)
                || $purchaseOrder->closed_at !== null
                || (float) $item->received_quantity <= 0
                || $remaining < 0.001) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Only a partially received, open purchase order can be closed short.',
                ]);
            }

            $ingredient = Ingredient::query()->findOrFail($item->ingredient_id);
            $creditAmount = $item->expected_unit_cost === null
                ? null
                : round($remaining * (float) $item->expected_unit_cost, 2);
            $memo = "Credit memo draft: {$remaining} {$ingredient->unit} {$ingredient->name} unreceived";
            $memo .= $creditAmount === null ? ' (amount unavailable).' : ' (₱'.number_format($creditAmount, 2).').';
            $purchaseOrder->update([
                'status' => 'closed_short',
                'closed_at' => now(),
                'notes' => trim(implode("\n", array_filter([$purchaseOrder->notes, $memo]))),
            ]);
            $this->recordActivity(
                $request,
                'Receiving',
                "Closed {$purchaseOrder->reference} short for {$ingredient->name}",
                $purchaseOrder,
                ['remaining_quantity' => $remaining, 'credit_amount' => $creditAmount],
            );

            return $creditAmount === null
                ? 'Short delivery closed. Credit amount is unavailable because no expected unit cost was recorded.'
                : 'Credit memo drafted: ₱'.number_format($creditAmount, 2);
        });

        return back()->with('status', $message);
    }

    public function barcode(): View
    {
        $purchaseOrders = $this->purchaseOrdersWithItems();
        $ingredients = Ingredient::query()->whereNotNull('barcode')->orderBy('name')->get();

        return view('admin.barcode', [
            'purchaseOrders' => $purchaseOrders,
            'ingredients' => $ingredients,
            'receiptToken' => (string) Str::uuid(),
        ]);
    }

    public function lookupBarcode(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:64'],
        ]);
        $barcode = trim($validated['barcode']);
        $resolved = $inventory->resolveBarcode($barcode);

        if ($resolved === null) {
            return response()->json(['message' => 'This barcode is not registered to an active package or ingredient.'], 404);
        }

        $purchaseOrderItem = PurchaseOrderItem::query()
            ->with(['purchaseOrder', 'ingredient.stockLots'])
            ->where('ingredient_id', $resolved['ingredient']->id)
            ->whereColumn('received_quantity', '<', 'ordered_quantity')
            ->whereHas('purchaseOrder', fn (Builder $query) => $query
                ->whereIn('status', ['open', 'partial'])
                ->whereNull('closed_at'))
            ->orderBy('id')
            ->first();

        if ($purchaseOrderItem === null) {
            return response()->json([
                'message' => 'No open purchase order for '.$resolved['ingredient']->name.'. Receiving is blocked until a purchase order exists.',
            ], 409);
        }

        $remaining = round(
            (float) $purchaseOrderItem->ordered_quantity - (float) $purchaseOrderItem->received_quantity,
            3,
        );
        $packageQuantity = $resolved['package'] === null
            ? null
            : (float) $resolved['package']->package_quantity;
        $defaultQuantity = $packageQuantity === null ? $remaining : min($packageQuantity, $remaining);
        $lastLot = $resolved['ingredient']->stockLots
            ->sortByDesc('received_at')
            ->first();

        return response()->json([
            'barcode' => $barcode,
            'ingredient' => [
                'id' => $resolved['ingredient']->id,
                'name' => $resolved['ingredient']->name,
                'unit' => $resolved['ingredient']->unit,
            ],
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'purchase_order' => [
                'reference' => $purchaseOrderItem->purchaseOrder->reference,
                'supplier' => $purchaseOrderItem->purchaseOrder->supplier_name,
                'ordered_quantity' => (float) $purchaseOrderItem->ordered_quantity,
                'received_quantity' => (float) $purchaseOrderItem->received_quantity,
                'remaining_quantity' => $remaining,
            ],
            'current_stock' => (float) $resolved['ingredient']->current_stock,
            'unit_cost' => $resolved['ingredient']->unit_cost === null
                ? null
                : (float) $resolved['ingredient']->unit_cost,
            'default_quantity' => $defaultQuantity,
            'storage_zone' => $lastLot?->storage_zone,
        ]);
    }

    public function receivePackage(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:64'],
            'purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'invoice_number' => ['required', 'string', 'max:80'],
            'batch_number' => ['required', 'string', 'max:120'],
            'expires_at' => ['required', 'date'],
            'storage_zone' => ['nullable', 'string', 'max:120'],
            'receipt_token' => ['required', 'uuid'],
        ]);

        try {
            $receipt = $inventory->receivePurchaseOrderPackage(
                trim($validated['barcode']),
                (int) $validated['purchase_order_item_id'],
                (float) $validated['quantity'],
                isset($validated['unit_cost']) ? (float) $validated['unit_cost'] : null,
                trim($validated['invoice_number']),
                trim($validated['batch_number']),
                $validated['expires_at'],
                $validated['storage_zone'] ?? null,
                $validated['receipt_token'],
                $request->user(),
            );
        } catch (\DomainException $exception) {
            $status = $exception->getCode() === 409 ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Receipt confirmed. Stock, lot traceability, and purchase-order quantities were updated.',
            'ingredient' => $receipt['ingredient']->name,
            'purchase_order' => $receipt['purchase_order']->reference,
            'received_quantity' => $receipt['received_quantity'],
            'current_stock' => $receipt['current_stock'],
            'expected_stock' => $receipt['expected_stock'],
            'remaining_quantity' => $receipt['remaining_quantity'],
            'next_receipt_token' => (string) Str::uuid(),
        ], 201);
    }

    public function lots(): View
    {
        $lots = InventoryStockLot::query()
            ->with(['ingredient', 'purchaseOrderItem.purchaseOrder'])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get();

        return view('admin.lots', compact('lots'));
    }

    public function waste(): View
    {
        $ingredients = Ingredient::query()->orderBy('name')->get();
        $logs = InventoryLog::query()
            ->with(['ingredient', 'user'])
            ->where('type', 'waste')
            ->latest()
            ->get();
        $lossByStage = InventoryLog::query()
            ->select('stage')
            ->selectRaw('SUM(cost_amount) as stage_cost')
            ->where('type', 'waste')
            ->whereNotNull('cost_amount')
            ->groupBy('stage')
            ->get()
            ->mapWithKeys(fn (InventoryLog $log): array => [
                $log->stage ?: 'Unspecified' => (float) $log->getAttribute('stage_cost'),
            ]);
        $nearExpiry = InventoryStockLot::query()
            ->with('ingredient')
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', today()->addDays(5))
            ->orderBy('expires_at')
            ->get();
        $wasteStages = InventoryTaxonomy::wasteStages();
        $wasteReasons = InventoryTaxonomy::wasteReasons();

        return view('admin.waste', compact('ingredients', 'logs', 'lossByStage', 'nearExpiry', 'wasteStages', 'wasteReasons'));
    }

    public function storeWaste(Request $request, InventoryService $inventory): RedirectResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'stage' => ['required', Rule::in(InventoryTaxonomy::wasteStages())],
            'reason' => ['required', Rule::in(InventoryTaxonomy::wasteReasons())],
            'other_reason' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn (): bool => $request->input('reason') === InventoryTaxonomy::OTHER_REASON)],
        ]);

        try {
            $reason = $validated['reason'] === InventoryTaxonomy::OTHER_REASON
                ? 'Others: '.trim((string) $validated['other_reason'])
                : $validated['reason'];
            $inventory->recordWaste(
                Ingredient::query()->findOrFail($validated['ingredient_id']),
                (float) $validated['quantity'],
                $reason,
                $reason,
                $request->user(),
                $validated['stage'],
            );
        } catch (\DomainException $exception) {
            return back()->withErrors(['quantity' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.waste')->with('status', 'Waste logged and stock updated.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PurchaseOrder>
     */
    private function purchaseOrdersWithItems(): Collection
    {
        return PurchaseOrder::query()
            ->with(['items.ingredient'])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->get();
    }

    private function recordActivity(
        Request $request,
        string $module,
        string $action,
        Model $subject,
        array $metadata = [],
    ): void {
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'module' => $module,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
