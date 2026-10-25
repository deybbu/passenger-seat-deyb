@extends('layouts.admin')

@section('title', 'Inventory')
@section('page-title', 'Inventory')

@section('content')
    <div class="admin-inventory-page">
        <div class="admin-inventory-stats">
            <div class="admin-card admin-inventory-stat"><small>Ingredients</small><strong>{{ $ingredients->count() }}</strong></div>
            <div class="admin-card admin-inventory-stat"><small>Low stock</small><strong>{{ $lowStockCount }}</strong></div>
            <div class="admin-card admin-inventory-stat"><small>Out of stock</small><strong>{{ $outOfStockCount }}</strong></div>
            <div class="admin-card admin-inventory-stat"><small>Stock value</small><strong>{{ $stockValue === null ? '—' : '₱'.number_format($stockValue, 2) }}</strong></div>
        </div>

        <details class="admin-card admin-inventory-register" id="register-ingredient">
            <summary class="admin-card-title">Register a new ingredient</summary>
            <form method="POST" action="{{ route('admin.inventory.store') }}" class="admin-form-grid">
                @csrf
                <label class="admin-field">Ingredient name
                    <input type="text" name="name" maxlength="120" value="{{ old('name') }}" required>
                </label>
                <label class="admin-field">Opening stock
                    <input type="number" name="current_stock" min="0" step="0.001" value="{{ old('current_stock') }}" required>
                </label>
                <label class="admin-field">Unit
                    <input type="text" name="unit" maxlength="20" value="{{ old('unit') }}" required placeholder="kg, L, pcs">
                </label>
                <label class="admin-field">Unit cost (₱)
                    <input type="number" name="unit_cost" min="0" step="0.0001" value="{{ old('unit_cost') }}">
                </label>
                <label class="admin-field">Low-stock threshold
                    <input type="number" name="min_threshold" min="0" step="0.001" value="{{ old('min_threshold') }}" required>
                </label>
                <label class="admin-field">Barcode
                    <input type="text" name="barcode" maxlength="64" value="{{ old('barcode') }}">
                </label>
                <div class="admin-recipe-form-action"><button type="submit" class="btn-red">Save ingredient</button></div>
            </form>
        </details>

        <section class="admin-card">
            <div class="admin-card-header admin-inventory-heading">
                <span class="admin-card-title">Ingredient inventory</span>
                <span class="admin-muted">Click an alert row to reorder</span>
                <a href="{{ route('admin.barcode') }}" class="btn-red">Receive package</a>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-data-table admin-inventory-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Ingredient</th>
                            <th>Stock</th>
                            <th>Min</th>
                            <th>Days left</th>
                            <th>Bin</th>
                            <th>Next expiry</th>
                            <th>Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ingredients as $ingredient)
                            @php
                                $status = $ingredient->admin_stock_status;
                                $daysLeft = $ingredient->admin_days_left;
                                $isAlert = $status !== 'OK' || ($daysLeft !== null && $daysLeft <= 3);
                                $recommended = (int) ceil(max(
                                    ((float) $ingredient->min_threshold * 2) - (float) $ingredient->admin_usable_stock,
                                    (float) $ingredient->min_threshold,
                                ));
                            @endphp
                            <tr
                                @if($isAlert)
                                    class="admin-inventory-alert-row"
                                    tabindex="0"
                                    role="button"
                                    data-reorder-trigger
                                    data-ingredient-id="{{ $ingredient->id }}"
                                    data-ingredient-name="{{ $ingredient->name }}"
                                    data-stock="{{ number_format((float) $ingredient->admin_usable_stock, 3, '.', '') }}"
                                    data-minimum="{{ number_format((float) $ingredient->min_threshold, 3, '.', '') }}"
                                    data-days="{{ $daysLeft === null ? '—' : number_format((float) $daysLeft, 1) }}"
                                    data-unit="{{ $ingredient->unit }}"
                                    data-recommended="{{ $recommended }}"
                                @endif
                            >
                                <td><code>{{ $ingredient->barcode ? substr($ingredient->barcode, -4) : '—' }}</code></td>
                                <td class="admin-strong">{{ $ingredient->name }}</td>
                                <td>{{ number_format((float) $ingredient->admin_usable_stock, 3) }} {{ $ingredient->unit }}</td>
                                <td>{{ number_format((float) $ingredient->min_threshold, 3) }} {{ $ingredient->unit }}</td>
                                <td>{{ $daysLeft === null ? '—' : number_format((float) $daysLeft, 1).' d' }}</td>
                                <td>{{ $ingredient->admin_storage_zone ?: '—' }}</td>
                                <td>{{ $ingredient->admin_next_expiry?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $ingredient->unit_cost === null ? '—' : '₱'.number_format((float) $ingredient->admin_usable_stock * (float) $ingredient->unit_cost, 2) }}</td>
                                <td>
                                    <span class="admin-inventory-status status-{{ strtolower($status) }}">
                                        {{ $status === 'Out' ? 'Out of stock' : ($status === 'Low' ? 'Low' : 'OK') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="admin-muted">No ingredients yet. Register an ingredient to start tracking stock.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Batch queue (FIFO)</span></div>
            <div class="admin-table-wrap">
                <table class="admin-data-table">
                    <thead>
                        <tr><th>Item</th><th>Batch</th><th>Qty</th><th>Received</th><th>Expiry</th><th>Order</th></tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $lot)
                            @php
                                $expired = $lot->expires_at !== null && $lot->expires_at->toDateString() < today()->toDateString();
                                $isFirst = !$expired && ($firstUsableLotIds[$lot->ingredient_id] ?? null) === $lot->id;
                            @endphp
                            <tr>
                                <td class="admin-strong">{{ $lot->ingredient->name ?? 'Deleted ingredient' }}</td>
                                <td>{{ $lot->batch_number ?: '—' }}</td>
                                <td>{{ number_format((float) $lot->remaining_quantity, 3) }} {{ $lot->ingredient->unit ?? '' }}</td>
                                <td>{{ $lot->received_at?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $lot->expires_at?->format('Y-m-d') ?? '—' }}</td>
                                <td>
                                    <span class="admin-inventory-status {{ $expired ? 'status-out' : ($isFirst ? 'status-low' : 'status-ok') }}">
                                        {{ $expired ? 'Expired' : ($isFirst ? 'Use first' : 'Next') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="admin-muted">No batches recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <dialog class="admin-inventory-dialog" id="reorderDialog">
        <form method="POST" id="reorderForm" data-url-template="{{ route('admin.purchase-orders.store', ['ingredient' => '__INGREDIENT__']) }}">
            @csrf
            <button type="button" class="admin-inventory-dialog-close" data-close-reorder aria-label="Close">×</button>
            <h2><span id="reorderIngredientName"></span>: low stock</h2>
            <p id="reorderSummary" class="admin-muted"></p>
            <p>Recommended order: <strong id="reorderQuantity"></strong></p>
            <label class="admin-field">Supplier
                <input type="text" name="supplier_name" maxlength="150" required autocomplete="organization">
            </label>
            <div class="admin-inventory-dialog-actions">
                <button type="button" class="btn-outline" data-close-reorder>Close</button>
                <button type="submit" class="btn-red">Generate purchase order</button>
            </div>
        </form>
    </dialog>
@endsection
