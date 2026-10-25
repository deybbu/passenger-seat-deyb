<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\InventoryPackage;
use App\Models\InventoryReceipt;
use App\Models\InventoryStockLot;
use App\Models\InventoryPhysicalCount;
use App\Models\InventoryPhysicalCountItem;
use App\Models\InventoryIdempotencyKey;
use App\Models\ActivityLog;
use App\Models\MenuItem;
use App\Models\PreparationRecipe;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Http\Resources\InventoryBootstrapActivityResource;
use App\Http\Resources\InventoryBootstrapIngredientResource;
use App\Http\Resources\InventoryBootstrapLotResource;
use App\Http\Resources\InventoryBootstrapPackageResource;
use App\Http\Resources\InventoryBootstrapPurchaseOrderItemResource;
use App\Http\Resources\InventoryBootstrapRecipeResource;
use App\Services\InventoryService;
use App\Support\AdminSettings;
use App\Support\InventoryTaxonomy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class InventoryController extends Controller
{
    private const STORAGE_ZONES = ['Walk-in Freezer', 'Chiller', 'Dry Pantry Room'];

    public function index(Request $request): View
    {
        $ingredients = Ingredient::query()
            ->with(['stockLots' => fn ($query) => $query
                ->where('remaining_quantity', '>', 0)
                ->orderBy('received_at')
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('id')])
            ->orderBy('name')
            ->get();
        $ingredientIds = $ingredients->modelKeys();

        $supplierByIngredient = PurchaseOrderItem::query()
            ->with('purchaseOrder')
            ->whereIn('ingredient_id', $ingredientIds)
            ->orderByDesc('id')
            ->get()
            ->unique('ingredient_id')
            ->keyBy('ingredient_id');

        $logs = InventoryLog::query()
            ->with(['ingredient', 'user', 'stockLot.package', 'stockLot.purchaseOrderItem.purchaseOrder', 'order'])
            ->whereIn('ingredient_id', $ingredientIds)
            ->orderBy('id')
            ->get();
        $activityRows = collect(InventoryBootstrapActivityResource::collection($logs)->resolve());
        $movements = $activityRows->pluck('movement')->values();
        $audit = $activityRows->pluck('audit')->values();
        $auditActions = ActivityLog::query()
            ->with(['user', 'subject'])
            ->whereIn('module', ['inventory', 'Inventory', 'Receiving'])
            ->orderByDesc('id')
            ->get()
            ->map(function (ActivityLog $entry) use ($ingredients): ?array {
                $actionText = (string) $entry->action;
                $isCount = str_contains(strtolower($actionText), 'physical inventory count');
                $isPurchaseOrder = $entry->subject_type === PurchaseOrder::class
                    || in_array((string) $entry->module, ['Inventory', 'Receiving'], true);

                if (! $isCount && ! $isPurchaseOrder) {
                    return null;
                }

                $metadata = $entry->metadata ?? [];
                $ingredient = $ingredients->firstWhere('id', $metadata['ingredient_id'] ?? null);
                $reference = $entry->subject instanceof PurchaseOrder
                    ? (string) $entry->subject->reference
                    : 'COUNT-'.$entry->subject_id;

                return [
                    'id' => 'ACT-'.$entry->id,
                    'timestamp' => $entry->created_at?->toIso8601String() ?? '',
                    'staff' => (string) ($entry->user_id ?? 'System'),
                    'staffName' => (string) ($entry->user?->name ?? 'System'),
                    'action' => $isCount ? 'Physical count' : 'Purchase order',
                    'itemId' => (string) ($ingredient?->id ?? ''),
                    'itemName' => (string) ($ingredient?->name ?? ($isCount ? 'Physical inventory count' : 'Purchase order')),
                    'change' => 0.0,
                    'commissary' => (string) ($metadata['storage_zone'] ?? 'Restaurant'),
                    'bin' => '',
                    'note' => $actionText,
                    'ref' => $reference,
                    'cost' => 0.0,
                ];
            })
            ->filter()
            ->values();
        $audit = $audit->concat($auditActions)->sortByDesc('timestamp')->values();
        $wasteEvents = $activityRows->pluck('waste')->filter()->values();
        $netMovement = $movements
            ->reject(fn (array $movement): bool => $movement['kind'] === 'count_adjust')
            ->groupBy('itemId')
            ->map(fn ($rows): float => (float) $rows->sum('change'));

        foreach ($ingredients as $ingredient) {
            $supplier = $supplierByIngredient->get($ingredient->id)?->purchaseOrder?->supplier_name;
            $nextExpiry = $ingredient->stockLots
                ->filter(fn (InventoryStockLot $lot): bool => $lot->expires_at !== null)
                ->sortBy(fn (InventoryStockLot $lot): string => $lot->expires_at->toDateString())
                ->first()?->expires_at?->toDateString();

            $ingredient->setAttribute('preferred_supplier', $supplier);
            $ingredient->setAttribute('next_expiry', $nextExpiry);
            $ingredient->setAttribute('opening_stock', round(
                (float) $ingredient->current_stock - (float) $netMovement->get((string) $ingredient->id, 0),
                3,
            ));
            $usage = $logs
                ->where('ingredient_id', $ingredient->id)
                ->filter(fn (InventoryLog $log): bool => $log->type === 'stock_out'
                    && $log->order_id !== null
                    && $log->created_at?->greaterThanOrEqualTo(now()->subDays(30)))
                ->sum(fn (InventoryLog $log): float => abs((float) $log->quantity));
            $ingredient->setAttribute('usage_per_day', round((float) $usage / 30, 3));
        }

        $packages = InventoryPackage::query()
            ->with(['ingredient', 'stockLots.package', 'stockLots.purchaseOrderItem.purchaseOrder'])
            ->where('is_active', true)
            ->orderBy('label')
            ->get();
        $menuItems = MenuItem::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->with('ingredients')
            ->orderBy('name')
            ->get();
        $lots = InventoryStockLot::query()
            ->with(['package', 'purchaseOrderItem.purchaseOrder'])
            ->whereIn('ingredient_id', $ingredientIds)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->get();
        $purchaseOrderItems = PurchaseOrderItem::query()
            ->with(['purchaseOrder', 'ingredient'])
            ->whereIn('ingredient_id', $ingredientIds)
            ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('status', ['draft', 'open', 'partial', 'completed']))
            ->orderByDesc('id')
            ->get();
        $locations = collect()
            ->concat($lots->pluck('storage_zone'))
            ->concat($purchaseOrderItems->pluck('purchaseOrder.storage_zone'))
            ->filter(fn ($location): bool => is_string($location) && trim($location) !== '')
            ->unique()
            ->sort()
            ->values();
        if ($locations->isEmpty()) {
            $locations = collect(['Restaurant']);
        }

        $bootstrap = [
            'staff' => [
                'id' => (string) $request->user()->id,
                'name' => (string) auth()->user()->name,
                'role' => (string) auth()->user()->role,
            ],
            'locations' => $locations->all(),
            'items' => InventoryBootstrapIngredientResource::collection($ingredients)->resolve(),
            'recipes' => InventoryBootstrapRecipeResource::collection($menuItems)->resolve(),
            'packages' => InventoryBootstrapPackageResource::collection($packages)->resolve(),
            'batches' => InventoryBootstrapLotResource::collection($lots)->resolve(),
            'purchaseOrders' => InventoryBootstrapPurchaseOrderItemResource::collection($purchaseOrderItems)->resolve(),
            'movements' => $movements->all(),
            'audit' => $audit->all(),
            'wasteEvents' => $wasteEvents->all(),
            'physicalCounts' => [],
            'pendingCounts' => InventoryPhysicalCount::query()->with(['counter', 'items.ingredient'])
                ->where('status', 'pending_approval')->latest('submitted_at')->limit(20)->get()
                ->map(fn (InventoryPhysicalCount $count): array => [
                    'id' => (string) $count->id,
                    'storageZone' => (string) ($count->storage_zone ?? 'Restaurant'),
                    'countedBy' => (string) ($count->counter?->name ?? 'Staff'),
                    'submittedAt' => $count->submitted_at?->toIso8601String(),
                    'itemCount' => $count->items->count(),
                ])->all(),
            'wasteStages' => InventoryTaxonomy::wasteStages(),
            'wasteReasons' => InventoryTaxonomy::wasteReasons(),
            'varianceReasons' => InventoryTaxonomy::varianceReasons(),
            'targetPct' => (float) (AdminSettings::all()['recipe_target_percent'] ?? 30),
        ];

        return view('inventory.index', [
            'inventoryBootstrap' => $bootstrap,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }

    public function confirmBatchReceipt(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'manifest' => ['required', 'array'],
            'manifest.type' => ['required', 'in:passenger-seat-inventory-batch'],
            'manifest.reference' => ['required', 'string', 'max:80'],
            'manifest.items' => ['required', 'array', 'min:1', 'max:50'],
            'manifest.items.*.barcode' => ['required', 'string', 'max:64'],
            'manifest.items.*.manual_code' => ['required', 'string', 'max:64'],
            'manifest.items.*.ingredient_name' => ['required', 'string', 'max:120'],
            'manifest.items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'manifest.items.*.unit' => ['required', 'string', 'max:20'],
            'manifest.items.*.batch_number' => ['required', 'string', 'max:120'],
            'manifest.items.*.expires_at' => ['required', 'date_format:Y-m-d'],
            'manifest.items.*.receipt_token' => ['required', 'uuid'],
            'manifest.items.*.storage_zone' => ['required', 'string', 'max:80', Rule::in($this->storageZones())],
            'manifest.items.*.storage_bin' => ['nullable', 'string', 'max:80'],
            'manifest.signature' => ['required', 'string', 'size:64', 'regex:/\A[0-9a-f]{64}\z/'],
        ]);

        $manifest = $request->input('manifest');
        $signature = $manifest['signature'];
        unset($manifest['signature']);
        $canonical = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $expectedSignature = hash_hmac('sha256', $canonical, (string) config('app.key'));
        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'This combined QR signature is invalid. No stock was changed.'], 422);
        }

        $barcodes = array_column($manifest['items'], 'barcode');
        $receiptTokens = array_column($manifest['items'], 'receipt_token');
        if (count(array_unique($barcodes)) !== count($barcodes)
            || count(array_unique($receiptTokens)) !== count($receiptTokens)) {
            return response()->json(['message' => 'A combined QR cannot repeat a package or receipt token.'], 422);
        }

        try {
            $received = DB::transaction(function () use ($manifest, $inventory, $request): array {
                $ingredientIds = [];
                $results = [];

                foreach ($manifest['items'] as $line) {
                    $package = InventoryPackage::query()
                        ->with('ingredient')
                        ->where(fn ($query) => $query
                            ->where('barcode', $line['barcode'])
                            ->orWhere('manual_entry_code', $line['barcode']))
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if ($package === null
                        || ! in_array($line['manual_code'], [$package->manual_entry_code, $package->barcode], true)
                        || abs((float) $package->package_quantity - (float) $line['quantity']) > 0.0005
                        || (string) $package->ingredient->unit !== $line['unit']) {
                        throw new \DomainException('A package in this batch no longer matches its registered ingredient and quantity.');
                    }
                    if (in_array((int) $package->ingredient_id, $ingredientIds, true)) {
                        throw new \DomainException('A combined QR may include each ingredient only once.');
                    }
                    $ingredientIds[] = (int) $package->ingredient_id;

                    $receipt = $inventory->receiveRegisteredPackage(
                        $package->barcode,
                        $line['manual_code'],
                        (float) $line['quantity'],
                        $line['unit'],
                        null,
                        $line['receipt_token'],
                        $line['batch_number'],
                        $line['expires_at'],
                        $line['storage_zone'],
                        now()->toIso8601String(),
                        null,
                        null,
                        $line['storage_bin'] ?? null,
                        null,
                        $request->user(),
                    );
                    if ($receipt['replayed'] ?? false) {
                        throw new \DomainException('This delivery batch was already confirmed.', 409);
                    }

                    $results[] = [
                        'ingredient' => (string) $receipt['ingredient']->name,
                        'quantity' => (float) $receipt['package_quantity'],
                        'unit' => (string) $receipt['ingredient']->unit,
                        'stock' => (float) $receipt['ingredient']->current_stock,
                    ];
                }

                return $results;
            });
        } catch (\DomainException $exception) {
            $status = $exception->getCode() === 409 ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        } catch (QueryException $exception) {
            $existingReceipt = InventoryReceipt::query()
                ->whereIn('idempotency_key', $receiptTokens)
                ->exists();
            $existingPackage = InventoryReceipt::query()
                ->whereHas('package', fn ($query) => $query->whereIn('barcode', $barcodes))
                ->exists();
            if (! $existingReceipt && ! $existingPackage) {
                throw $exception;
            }

            return response()->json(['message' => 'At least one package in this delivery was already received. No stock was changed.'], 409);
        }

        return response()->json([
            'message' => 'All '.count($received).' ingredients were received successfully. Inventory has been updated.',
            'receipts' => $received,
        ], 201);
    }

    public function syncOfflineActions(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.idempotency_key' => ['required', 'uuid'],
            'events.*.operation' => ['required', Rule::in([
                'receipt.confirm', 'waste.store', 'purchase-order.create', 'physical-count.submit',
            ])],
            'events.*.payload' => ['required', 'array'],
            'events.*.staff_id' => ['required', 'integer'],
        ]);

        $results = [];
        foreach ($validated['events'] as $event) {
            if ((int) $event['staff_id'] !== (int) $request->user()->id) {
                $results[] = [
                    'idempotency_key' => $event['idempotency_key'],
                    'status_code' => 403,
                    'body' => ['message' => 'Sign in as the staff member who created this queued action.'],
                    'replayed' => false,
                ];
                continue;
            }

            $results[] = DB::transaction(function () use ($request, $inventory, $event): array {
                $payloadJson = json_encode($event['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $requestHash = hash('sha256', $event['operation']."\n".$payloadJson);
                $now = now();

                InventoryIdempotencyKey::query()->insertOrIgnore([
                    'idempotency_key' => $event['idempotency_key'],
                    'user_id' => $request->user()->id,
                    'operation' => $event['operation'],
                    'request_hash' => $requestHash,
                    'status' => 'processing',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $key = InventoryIdempotencyKey::query()
                    ->where('idempotency_key', $event['idempotency_key'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $key->user_id !== (int) $request->user()->id
                    || $key->operation !== $event['operation']
                    || ! hash_equals($key->request_hash, $requestHash)) {
                    return [
                        'idempotency_key' => $event['idempotency_key'],
                        'status_code' => 409,
                        'body' => ['message' => 'This idempotency key was already used for a different action.'],
                        'replayed' => false,
                    ];
                }

                if ($key->status === 'completed') {
                    return [
                        'idempotency_key' => $event['idempotency_key'],
                        'status_code' => (int) ($key->response_code ?? 200),
                        'body' => $key->response_payload ?? [],
                        'replayed' => true,
                    ];
                }

                $eventRequest = Request::create('/staff/inventory/sync-replay', 'POST', $event['payload'], [], [], [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ]);
                $eventRequest->setUserResolver(fn () => $request->user());

                try {
                    $response = match ($event['operation']) {
                        'receipt.confirm' => $this->confirmReceipt($eventRequest, $inventory),
                        'waste.store' => $this->storeWasteEntry($eventRequest, $inventory),
                        'purchase-order.create' => $this->storePurchaseOrder($eventRequest, $inventory),
                        'physical-count.submit' => $this->storePhysicalCount($eventRequest, $inventory),
                    };
                    $statusCode = $response->getStatusCode();
                    $body = json_decode($response->getContent(), true) ?: [];
                } catch (ValidationException $exception) {
                    $statusCode = 422;
                    $body = ['message' => $exception->getMessage(), 'errors' => $exception->errors()];
                }

                $key->update([
                    'status' => 'completed',
                    'response_code' => $statusCode,
                    'response_payload' => $body,
                    'completed_at' => now(),
                ]);

                return [
                    'idempotency_key' => $event['idempotency_key'],
                    'status_code' => $statusCode,
                    'body' => $body,
                    'replayed' => false,
                ];
            }, 3);
        }

        return response()->json(['results' => $results]);
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

    public function storePurchaseOrder(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unit' => ['required', 'string', 'max:20'],
            'conversion_factor' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'supplier_name' => ['required', 'string', 'max:150'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:today'],
            'storage_zone' => ['required', 'string', 'max:80'],
        ]);
        $ingredient = Ingredient::query()->findOrFail($validated['ingredient_id']);
        try {
            $quantity = $inventory->convertReceiptQuantity(
                (float) $validated['quantity'], $validated['unit'], (string) $ingredient->unit,
                isset($validated['conversion_factor']) ? (float) $validated['conversion_factor'] : null,
            );
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        if ($quantity < 0.001) {
            return response()->json(['message' => 'The converted order quantity must be at least 0.001 '.$ingredient->unit.'.'], 422);
        }

        $order = DB::transaction(function () use ($request, $ingredient, $validated, $quantity): PurchaseOrder {
            $order = PurchaseOrder::query()->create([
                'reference' => 'PO-'.Str::ulid(),
                'supplier_name' => trim($validated['supplier_name']),
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'storage_zone' => $validated['storage_zone'],
                'expected_at' => $validated['expected_at'] ?? null,
            ]);
            $order->items()->create([
                'ingredient_id' => $ingredient->id,
                'ordered_quantity' => $quantity,
                'received_quantity' => 0,
                'expected_unit_cost' => $ingredient->unit_cost,
            ]);
            return $order->load('items');
        });

        return response()->json([
            'message' => 'Purchase order draft created; manager approval is required before receiving.',
            'purchase_order' => [
                'id' => $order->reference, 'orderId' => (string) $order->id, 'lineId' => (string) $order->items->first()->id,
                'commissary' => $order->storage_zone, 'itemId' => (string) $ingredient->id,
                'qty' => $quantity, 'received' => 0, 'supplier' => $order->supplier_name,
                'due' => (string) ($order->expected_at?->toDateString() ?? ''), 'status' => 'draft',
            ],
        ], 201);
    }

    public function approvePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        abort_unless(in_array((string) $request->user()->role, ['manager', 'admin'], true), 403);
        if ($purchaseOrder->status !== 'draft' || $purchaseOrder->items()->doesntExist()) {
            return response()->json(['message' => 'Only a non-empty draft purchase order can be approved.'], 409);
        }
        $purchaseOrder->update([
            'status' => 'open', 'approved_by' => $request->user()->id,
            'approved_at' => now(), 'ordered_at' => now(),
        ]);
        return response()->json(['message' => 'Purchase order approved and open for receiving.', 'status' => 'open']);
    }

    public function storeWasteEntry(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unit' => ['required', 'string', 'max:20'],
            'conversion_factor' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'stage' => ['required', Rule::in(InventoryTaxonomy::wasteStages())],
            'reason' => ['required', Rule::in(InventoryTaxonomy::wasteReasons())],
            'other_reason' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn (): bool => $request->input('reason') === InventoryTaxonomy::OTHER_REASON)],
            'notes' => ['nullable', 'string', 'max:500'],
            'storage_zone' => ['nullable', 'string', 'max:80'],
        ]);
        $ingredient = Ingredient::query()->findOrFail($validated['ingredient_id']);
        try {
            $quantity = $inventory->convertReceiptQuantity(
                (float) $validated['quantity'], $validated['unit'], (string) $ingredient->unit,
                isset($validated['conversion_factor']) ? (float) $validated['conversion_factor'] : null,
            );
            $reason = $validated['reason'] === InventoryTaxonomy::OTHER_REASON
                ? 'Others: '.trim((string) $validated['other_reason'])
                : $validated['reason'];
            $details = trim(implode(' · ', array_filter([
                $validated['notes'] ?? null,
                isset($validated['storage_zone']) ? 'Location: '.$validated['storage_zone'] : null,
            ]))) ?: $reason;
            $beforeId = (int) InventoryLog::query()->max('id');
            $cost = $inventory->recordWaste($ingredient, $quantity, $reason, $details, $request->user(), $validated['stage']);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $logs = InventoryLog::query()->with(['ingredient', 'user', 'stockLot'])
            ->where('id', '>', $beforeId)->where('ingredient_id', $ingredient->id)
            ->where('user_id', $request->user()->id)->where('type', 'waste')->get();
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'Inventory waste recorded', 'module' => 'inventory',
            'subject_type' => Ingredient::class, 'subject_id' => $ingredient->id,
            'metadata' => ['quantity' => $quantity, 'reason' => $reason, 'stage' => $validated['stage'], 'cost_amount' => $cost],
        ]);

        return response()->json([
            'message' => 'Waste saved and stock updated.', 'cost' => $cost,
            'ingredient' => ['id' => (string) $ingredient->id, 'stock' => (float) $ingredient->fresh()->current_stock],
            'logs' => InventoryBootstrapActivityResource::collection($logs)->resolve(),
            'waste' => [
                'id' => 'WST-'.$logs->pluck('id')->implode('-'), 'timestamp' => now()->toIso8601String(),
                'staff' => (string) $request->user()->id, 'itemId' => (string) $ingredient->id,
                'qty' => $quantity, 'reason' => $reason, 'stage' => $validated['stage'],
                'commissary' => (string) ($validated['storage_zone'] ?? 'Restaurant'),
                'note' => $details, 'unitCost' => (float) ($ingredient->unit_cost ?? 0), 'loss' => (float) ($cost ?? 0),
            ],
        ], 201);
    }

    public function storePhysicalCount(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'storage_zone' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.ingredient_id' => ['required', 'integer', 'distinct', 'exists:ingredients,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.conversion_factor' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.reason' => ['nullable', Rule::in(InventoryTaxonomy::varianceReasons())],
            'items.*.waste_stage' => ['nullable', Rule::in(InventoryTaxonomy::wasteStages())],
        ]);
        $isManager = in_array((string) $request->user()->role, ['manager', 'admin'], true);

        try {
            $count = DB::transaction(function () use ($request, $validated, $inventory, $isManager): InventoryPhysicalCount {
                $count = InventoryPhysicalCount::query()->create([
                    'storage_zone' => $validated['storage_zone'] ?? null,
                    'status' => $isManager ? 'approved' : 'pending_approval',
                    'counted_by' => $request->user()->id,
                    'started_at' => now(), 'submitted_at' => now(),
                    'approved_by' => $isManager ? $request->user()->id : null,
                    'approved_at' => $isManager ? now() : null,
                ]);
                $rows = [];
                foreach ($validated['items'] as $line) {
                    $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($line['ingredient_id']);
                    $actual = (float) $line['quantity'] === 0.0 ? 0.0 : $inventory->convertReceiptQuantity(
                        (float) $line['quantity'], $line['unit'], (string) $ingredient->unit,
                        isset($line['conversion_factor']) ? (float) $line['conversion_factor'] : null,
                    );
                    $actual = round($actual, 3);
                    $system = round((float) $ingredient->current_stock, 3);
                    $variance = round($actual - $system, 3);
                    $reason = $line['reason'] ?? null;
                    if (abs($variance) >= 0.001 && ! $reason) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => "Select a reason for the {$ingredient->name} variance.",
                        ]);
                    }
                    if (str_starts_with((string) $reason, 'Waste: ') && empty($line['waste_stage'])) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => "Select a waste stage for the {$ingredient->name} count variance.",
                        ]);
                    }
                    $rows[] = [$ingredient, $actual, $system, $variance, $reason, $line['waste_stage'] ?? null];
                }

                foreach ($rows as [$ingredient, $actual, $system, $variance, $reason, $wasteStage]) {
                    $item = $count->items()->create([
                        'ingredient_id' => $ingredient->id,
                        'system_quantity' => $system, 'counted_quantity' => $actual,
                        'variance_quantity' => $variance, 'variance_reason' => $reason,
                        'waste_stage' => $wasteStage,
                        'adjusted' => false,
                    ]);
                    if ($isManager && abs($variance) >= 0.001) {
                        $this->applyPhysicalCountLine($inventory, $request, $item, $ingredient, $actual, $system, $reason, $wasteStage);
                    }
                }
                ActivityLog::query()->create([
                    'user_id' => $request->user()->id,
                    'action' => $isManager ? 'Physical inventory count applied' : 'Physical inventory count submitted',
                    'module' => 'inventory', 'subject_type' => InventoryPhysicalCount::class,
                    'subject_id' => $count->id,
                    'metadata' => ['storage_zone' => $count->storage_zone, 'items' => count($rows), 'status' => $count->status],
                ]);
                return $count->load(['counter', 'items.ingredient']);
            }, 3);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => $isManager ? 'Physical count saved and adjustments applied.' : 'Physical count submitted for manager approval.',
            'count' => ['id' => (string) $count->id, 'status' => (string) $count->status, 'storageZone' => (string) ($count->storage_zone ?? 'Restaurant'), 'countedBy' => (string) ($count->counter?->name ?? $request->user()->name), 'submittedAt' => $count->submitted_at?->toIso8601String(), 'itemCount' => $count->items->count()],
            'stocks' => $count->items->mapWithKeys(fn (InventoryPhysicalCountItem $line): array => [(string) $line->ingredient_id => (float) $line->ingredient->fresh()->current_stock])->all(),
        ], 201);
    }

    public function approvePhysicalCount(Request $request, InventoryPhysicalCount $physicalCount, InventoryService $inventory): JsonResponse
    {
        abort_unless(in_array((string) $request->user()->role, ['manager', 'admin'], true), 403);
        try {
            DB::transaction(function () use ($request, $physicalCount, $inventory): void {
                $count = InventoryPhysicalCount::query()->with('items')->lockForUpdate()->findOrFail($physicalCount->id);
                if ($count->status !== 'pending_approval') {
                    throw new \DomainException('This count has already been approved or is not awaiting approval.');
                }
                foreach ($count->items as $item) {
                    if ($item->counted_quantity === null) {
                        continue;
                    }
                    $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($item->ingredient_id);
                    $actual = (float) $item->counted_quantity;
                    $system = round((float) $ingredient->current_stock, 3);
                    if (abs($system - (float) $item->system_quantity) >= 0.001) {
                        throw new \DomainException("Stock for {$ingredient->name} changed after the count was submitted. Recount it before approval.");
                    }
                    if (abs($actual - $system) >= 0.001) {
                        $this->applyPhysicalCountLine($inventory, $request, $item, $ingredient, $actual, $system, $item->variance_reason, $item->waste_stage);
                    }
                }
                $count->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
                ActivityLog::query()->create([
                    'user_id' => $request->user()->id, 'action' => 'Physical inventory count approved',
                    'module' => 'inventory', 'subject_type' => InventoryPhysicalCount::class,
                    'subject_id' => $count->id, 'metadata' => ['storage_zone' => $count->storage_zone, 'items' => $count->items->count()],
                ]);
            }, 3);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
        $count = $physicalCount->fresh()->load('items');
        return response()->json([
            'message' => 'Count approved and inventory adjustments applied.',
            'stocks' => $count->items->mapWithKeys(fn (InventoryPhysicalCountItem $line): array => [(string) $line->ingredient_id => (float) Ingredient::query()->find($line->ingredient_id)?->current_stock])->all(),
        ]);
    }

    private function applyPhysicalCountLine(
        InventoryService $inventory,
        Request $request,
        InventoryPhysicalCountItem $line,
        Ingredient $ingredient,
        float $actual,
        float $system,
        ?string $reason,
        ?string $wasteStage,
    ): void {
        $delta = round($actual - $system, 3);
        $logId = null;
        if ($delta < -0.0001 && str_starts_with((string) $reason, 'Waste: ')) {
            $wasteReason = substr((string) $reason, 7);
            if ($wasteReason === 'Other') {
                $wasteReason = 'Others: count variance';
            }
            $inventory->recordWaste($ingredient, abs($delta), $wasteReason, 'Recorded from approved physical-count variance.', $request->user(), $wasteStage ?? InventoryTaxonomy::wasteStages()[0]);
            $logId = InventoryLog::query()->where('ingredient_id', $ingredient->id)->where('user_id', $request->user()->id)->where('type', 'waste')->latest('id')->value('id');
        } else {
            $inventory->override($ingredient, $actual, $request->user(), 'Physical count '.$line->physical_count_id.'; variance reason: '.($reason ?: 'No variance'));
            $logId = InventoryLog::query()->where('ingredient_id', $ingredient->id)->where('user_id', $request->user()->id)->where('type', 'adjustment')->latest('id')->value('id');
        }
        $line->update(['adjusted' => true, 'inventory_log_id' => $logId]);
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
            'stage' => ['required', Rule::in(InventoryTaxonomy::wasteStages())],
            'reason' => ['required', Rule::in(InventoryTaxonomy::wasteReasons())],
            'other_reason' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn (): bool => $request->input('reason') === InventoryTaxonomy::OTHER_REASON)],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        try {
            $reason = $validated['reason'] === InventoryTaxonomy::OTHER_REASON
                ? 'Others: '.trim((string) $validated['other_reason'])
                : $validated['reason'];
            $estimatedCost = $inventory->recordWaste(
                Ingredient::query()->findOrFail($validated['ingredient_id']),
                (float) $validated['quantity'],
                $reason,
                trim($validated['notes']),
                $request->user(),
                $validated['stage'],
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
            'storage_zone' => ['required', 'string', 'max:80', Rule::in($this->storageZones())],
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
            'barcode' => ['nullable', 'string', 'max:64'],
            'manual_code' => ['nullable', 'string', 'max:64'],
            'payload' => ['nullable', 'string', 'max:4096'],
        ]);
        $raw = trim((string) ($validated['barcode'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($validated['manual_code'] ?? ''));
        }
        if ($raw === '' && isset($validated['payload'])) {
            $payload = json_decode($validated['payload'], true);
            if (! is_array($payload)) {
                return response()->json(['message' => 'QR metadata must be valid JSON.'], 422);
            }
            $raw = trim((string) ($payload['code'] ?? $payload['qrCode'] ?? $payload['barcode'] ?? $payload['manualCode'] ?? $payload['manualEntryCode'] ?? ''));
        }
        if ($raw === '') {
            return response()->json(['message' => 'Enter a registered package code or manual entry code.'], 422);
        }
        $resolved = $inventory->resolveBarcode($raw);

        if ($resolved === null) {
            return response()->json([
                'message' => 'This code is not registered to an active package.',
                'barcode' => $raw,
                'can_register' => false,
            ], 404);
        }

        $ingredient = $resolved['ingredient'];
        $package = $resolved['package'];
        if ($package === null) {
            return response()->json(['message' => 'Receiving requires a registered package QR or manual code.'], 404);
        }
        $receipt = InventoryReceipt::query()->where('package_id', $package->id)->first();
        $existingLot = InventoryStockLot::query()->where('package_id', $package->id)->latest('received_at')->first();
        if ($receipt !== null || $existingLot !== null) {
            $receivedAt = $receipt?->received_at ?? $existingLot?->received_at;
            return response()->json([
                'message' => 'This package was already received'.($receivedAt ? ' on '.$receivedAt->timezone('Asia/Manila')->format('M j, Y g:i A') : '').'.',
                'received_at' => $receivedAt?->toIso8601String(),
            ], 409);
        }
        $quantity = (float) $package->package_quantity;
        $current = round((float) $ingredient->current_stock, 3);

        return response()->json([
            'barcode' => $package->barcode,
            'manual_code' => $package->manual_entry_code ?: $package->barcode,
            'ingredient' => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'unit' => $ingredient->unit,
            ],
            'package' => [
                'id' => $package->id,
                'label' => $package->label,
                'quantity' => $quantity,
            ],
            'batch' => null,
            'best_before' => null,
            'manufacturer' => null,
            'serial' => null,
            'commissary' => null,
            'bin' => null,
            'purchase_order_item_id' => null,
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
            'unit' => ['nullable', 'string', 'max:20'],
            'conversion_factor' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        try {
            $resolved = $inventory->resolveBarcode(trim($validated['barcode']));
            if ($resolved === null || $resolved['package'] === null) {
                throw new \DomainException('This code is not registered to an active package.');
            }
            $ingredient = $resolved['ingredient'];
            $enteredQuantity = isset($validated['quantity']) ? (float) $validated['quantity'] : (float) $resolved['package']->package_quantity;
            $unit = $validated['unit'] ?? $ingredient->unit;
            $converted = $inventory->convertReceiptQuantity($enteredQuantity, $unit, (string) $ingredient->unit, isset($validated['conversion_factor']) ? (float) $validated['conversion_factor'] : null);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'current_stock' => round((float) $ingredient->current_stock, 3),
            'package_quantity' => $converted,
            'expected_stock' => round((float) $ingredient->current_stock + $converted, 3),
            'unit' => $ingredient->unit,
        ]);
    }

    public function confirmReceipt(Request $request, InventoryService $inventory): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:64'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'receipt_token' => ['required', 'uuid'],
            'manual_code' => ['required', 'string', 'max:64'],
            'unit' => ['required', 'string', 'max:20'],
            'conversion_factor' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'batch_number' => ['required', 'string', 'max:120'],
            'expires_at' => ['required', 'date'],
            'storage_zone' => ['required', 'string', 'max:80', Rule::in($this->storageZones())],
            'storage_bin' => ['nullable', 'string', 'max:80'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'scanned_at' => ['nullable', 'date'],
        ]);

        try {
            $receipt = $inventory->receiveRegisteredPackage(
                trim($validated['barcode']),
                trim($validated['manual_code']),
                isset($validated['quantity']) ? (float) $validated['quantity'] : 0,
                $validated['unit'],
                isset($validated['conversion_factor']) ? (float) $validated['conversion_factor'] : null,
                $validated['receipt_token'],
                trim($validated['batch_number']),
                $validated['expires_at'],
                $validated['storage_zone'],
                $validated['scanned_at'] ?? null,
                $validated['manufacturer'] ?? null,
                $validated['serial_number'] ?? null,
                $validated['storage_bin'] ?? null,
                isset($validated['purchase_order_item_id']) ? (int) $validated['purchase_order_item_id'] : null,
                $request->user(),
            );
        } catch (\DomainException $exception) {
            $status = $exception->getCode() === 409 ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        } catch (QueryException $exception) {
            $duplicatePackageReceipt = InventoryReceipt::query()
                ->whereHas('package', fn ($query) => $query->where('barcode', trim($validated['barcode']))
                    ->orWhere('manual_entry_code', trim($validated['barcode'])))
                ->exists();
            $duplicatePackageLot = InventoryStockLot::query()
                ->whereHas('package', fn ($query) => $query->where('barcode', trim($validated['barcode']))
                    ->orWhere('manual_entry_code', trim($validated['barcode'])))
                ->exists();
            if (! $duplicatePackageReceipt && ! $duplicatePackageLot && ! InventoryStockLot::query()->where('receipt_token', $validated['receipt_token'])->exists()) {
                throw $exception;
            }

            return response()->json([
                'message' => 'This package or receipt was already confirmed.',
            ], 409);
        }

        return response()->json([
            'message' => 'Receipt confirmed. Stock and the receiving lot have been recorded.',
            'ingredient' => [
                'id' => (string) $receipt['ingredient']->id,
                'name' => (string) $receipt['ingredient']->name,
                'stock' => (float) $receipt['ingredient']->current_stock,
                'unit' => (string) $receipt['ingredient']->unit,
                'exp' => (string) ($receipt['ingredient']->stockLots()->whereNotNull('expires_at')->orderBy('expires_at')->value('expires_at') ?? ''),
            ],
            'current_stock' => $receipt['current_stock'],
            'package_quantity' => $receipt['package_quantity'],
            'expected_stock' => $receipt['expected_stock'],
            'unit' => $receipt['ingredient']->unit,
            'audit' => $receipt['log'] ? [
                'id' => (string) $receipt['log']->id,
                'timestamp' => $receipt['log']->created_at?->toIso8601String() ?? '',
                'staff' => (string) ($receipt['log']->user_id ?? 'System'),
                'staffName' => (string) ($receipt['log']->user?->name ?? 'System'),
                'action' => 'QR received',
                'itemId' => (string) $receipt['ingredient']->id,
                'itemName' => (string) $receipt['ingredient']->name,
                'change' => (float) $receipt['package_quantity'],
                'commissary' => (string) ($receipt['lot']->storage_zone ?? 'Restaurant'),
                'bin' => (string) ($receipt['lot']->storage_bin ?? ''),
                'note' => (string) ($receipt['log']->notes ?? ''),
                'ref' => (string) $receipt['lot']->package?->barcode,
                'cost' => (float) ($receipt['log']->cost_amount ?? 0),
            ] : null,
            'movement' => $receipt['log'] ? [
                'id' => (string) $receipt['log']->id,
                'kind' => 'qr_received',
                'itemId' => (string) $receipt['ingredient']->id,
                'change' => (float) $receipt['package_quantity'],
                'timestamp' => $receipt['log']->created_at?->toIso8601String() ?? '',
                'ref' => (string) $receipt['lot']->package?->barcode,
                'note' => (string) ($receipt['log']->notes ?? ''),
                'commissary' => (string) ($receipt['lot']->storage_zone ?? 'Restaurant'),
            ] : null,
            'lot' => [
                'id' => (string) $receipt['lot']->id,
                'itemId' => (string) $receipt['lot']->ingredient_id,
                'code' => (string) $receipt['lot']->package?->barcode,
                'manualCode' => (string) ($receipt['lot']->package?->manual_entry_code ?? ''),
                'batch' => (string) $receipt['lot']->batch_number,
                'qty' => (float) $receipt['lot']->received_quantity,
                'remainingQty' => (float) $receipt['lot']->remaining_quantity,
                'bestBefore' => (string) ($receipt['lot']->expires_at?->toDateString() ?? ''),
                'receivedAt' => $receipt['lot']->received_at?->toIso8601String(),
                'scannedAt' => $receipt['lot']->scanned_at?->toIso8601String(),
                'manufacturer' => (string) ($receipt['lot']->manufacturer ?? ''),
                'serial' => (string) ($receipt['lot']->serial_number ?? ''),
                'commissary' => (string) ($receipt['lot']->storage_zone ?? ''),
                'bin' => (string) ($receipt['lot']->storage_bin ?? ''),
                'poId' => (string) ($receipt['purchase_order']?->reference ?? ''),
                'source' => 'QR receipt',
            ],
            'purchase_order' => $receipt['purchase_order'] ? [
                'id' => (string) $receipt['purchase_order']->reference,
                'lineId' => (string) $receipt['purchase_order_item']->id,
                'received' => (float) $receipt['purchase_order_item']->received_quantity,
                'status' => (string) $receipt['purchase_order']->status,
            ] : null,
            'next_receipt_token' => (string) Str::uuid(),
            'replayed' => (bool) ($receipt['replayed'] ?? false),
        ], ($receipt['replayed'] ?? false) ? 200 : 201);
    }

    public function registerPackage(Request $request): JsonResponse
    {
        abort_unless(in_array((string) $request->user()->role, ['manager', 'admin'], true), 403);
        $request->merge([
            'barcode' => trim((string) $request->input('barcode', '')) ?: null,
            'manual_entry_code' => trim((string) $request->input('manual_entry_code', '')) ?: null,
        ]);
        $validated = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:inventory_packages,barcode'],
            'manual_entry_code' => ['nullable', 'string', 'max:64', 'unique:inventory_packages,manual_entry_code'],
            'label' => ['required', 'string', 'max:120'],
            'package_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ]);
        $package = DB::transaction(function () use ($validated, $request): InventoryPackage {
            $barcode = $validated['barcode'] ?: $this->uniquePackageCode('PSP-');
            $manualCode = $validated['manual_entry_code'] ?: $this->uniquePackageCode('PSM-');
            $codeCollision = $barcode === $manualCode
                || Ingredient::query()->where('barcode', $barcode)->orWhere('barcode', $manualCode)->exists()
                || InventoryPackage::query()->where('barcode', $manualCode)->orWhere('manual_entry_code', $barcode)->exists();
            if ($codeCollision) {
                throw \Illuminate\Validation\ValidationException::withMessages(['barcode' => 'The QR and manual codes must be unique across all package and ingredient codes.']);
            }
            return InventoryPackage::query()->create([
                'ingredient_id' => $validated['ingredient_id'],
                'barcode' => $barcode,
                'manual_entry_code' => $manualCode,
                'label' => trim($validated['label']),
                'package_quantity' => $validated['package_quantity'],
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);
        });
        $package->load('ingredient');

        return response()->json([
            'message' => 'Package barcode registered.',
            'barcode' => $package->barcode,
            'manual_code' => $package->manual_entry_code,
            'label_url' => route('staff.inventory.packages.label', $package),
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

    public function packageLabel(Request $request, InventoryPackage $package): View
    {
        abort_unless(in_array((string) $request->user()->role, ['manager', 'admin'], true), 403);
        abort_unless($package->is_active, 404);
        $package->load('ingredient');
        $qrCode = QrCode::create($package->barcode)->setSize(260)->setMargin(12);
        $qrSvg = (new SvgWriter())->write($qrCode, null, null, [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true])->getString();

        return view('inventory.package-label', compact('package', 'qrSvg'));
    }

    private function uniquePackageCode(string $prefix): string
    {
        do {
            $code = $prefix.Str::upper(Str::random(12));
        } while (
            InventoryPackage::query()->where('barcode', $code)->orWhere('manual_entry_code', $code)->exists()
            || Ingredient::query()->where('barcode', $code)->exists()
        );

        return $code;
    }

    /** @return list<string> */
    private function storageZones(): array
    {
        return collect(self::STORAGE_ZONES)
            ->merge(['Restaurant'])
            ->merge(DB::table('inventory_stock_lots')->whereNotNull('storage_zone')->distinct()->pluck('storage_zone'))
            ->merge(DB::table('purchase_orders')->whereNotNull('storage_zone')->distinct()->pluck('storage_zone'))
            ->filter(fn ($zone): bool => is_string($zone) && trim($zone) !== '')
            ->map(fn (string $zone): string => trim($zone))
            ->unique()
            ->values()
            ->all();
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
