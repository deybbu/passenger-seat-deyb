@extends('layouts.admin')

@section('title', 'Inventory management')
@section('page-title', 'Inventory management')

@section('content')
    <div class="admin-action-bar">
        <a href="{{ route('admin.barcode') }}" class="btn-red">Receive package</a>
        <a href="#register-ingredient" class="btn-outline">Register ingredient</a>
        <a href="{{ route('admin.activity') }}" class="btn-outline">View activity</a>
    </div>

    <details class="admin-card" id="register-ingredient" style="margin-bottom:16px;">
        <summary class="admin-card-title" style="cursor:pointer;">Register a new ingredient</summary>
        <form method="POST" action="{{ route('admin.inventory.store') }}" class="admin-form-grid" style="margin-top:16px;">
            @csrf
            <label class="admin-field">Ingredient name
                <input type="text" name="name" maxlength="120" required>
            </label>
            <label class="admin-field">Opening stock
                <input type="number" name="current_stock" min="0" step="0.001" required>
            </label>
            <label class="admin-field">Unit
                <input type="text" name="unit" maxlength="20" required placeholder="kg, L, pcs">
            </label>
            <label class="admin-field">Low-stock threshold
                <input type="number" name="min_threshold" min="0" step="0.001" required>
            </label>
            <div><button type="submit" class="btn-red">Save ingredient</button></div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Ingredient inventory</span>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Ingredient</th>
                        <th>Stock</th>
                        <th>Min</th>
                        <th>Status</th>
                        <th>Receive stock</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($ingredients) && $ingredients->isNotEmpty())
                        @foreach($ingredients as $i => $ingredient)
                            @php
                                $stock = (float) $ingredient->current_stock;
                                $min = (float) $ingredient->min_threshold;
                                $isOut = $stock <= 0;
                                $isLow = !$ingredient->manual_override && $stock <= $min && $stock > 0;
                                
                                $statusClass = $isOut ? 'badge-out-of-stock' : ($isLow ? 'badge-low-stock' : 'badge-in-stock');
                                $statusLabel = $isOut ? 'Out of stock' : ($isLow ? 'Low stock' : 'In stock');
                                $code = 'ING-' . str_pad((string) $ingredient->id, 4, '0', STR_PAD_LEFT);
                            @endphp
                            <tr>
                                <td class="admin-muted">{{ $code }}</td>
                                <td class="admin-strong">{{ $ingredient->name }}</td>
                                <td>{{ rtrim(rtrim(number_format($stock, 1), '0'), '.') }} {{ $ingredient->unit }}</td>
                                <td>{{ rtrim(rtrim(number_format($min, 1), '0'), '.') }} {{ $ingredient->unit }}</td>
                                <td><span class="badge-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.inventory.log', $ingredient) }}" class="admin-inline-form">
                                        @csrf
                                        <input type="hidden" name="type" value="stock_in">
                                        <label class="admin-field">
                                            <span class="sr-only">Quantity to receive</span>
                                            <input type="number" name="quantity" min="0.001" step="0.001" required aria-label="Quantity to receive for {{ $ingredient->name }}" style="width:100px;">
                                        </label>
                                        <input type="hidden" name="notes" value="Inventory stock receipt">
                                        <button type="submit" class="btn-outline">Receive</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr><td colspan="6" class="admin-muted">No ingredients yet. Register an ingredient to start tracking stock.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection
