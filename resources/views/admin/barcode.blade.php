@extends('layouts.admin')

@section('title', 'Barcode receiving')
@section('page-title', 'Barcode receiving')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Receive stock by barcode</span></div>
        <form method="POST" action="{{ route('admin.barcode.receive') }}" class="admin-form-grid">
            @csrf
            <label class="admin-field">Barcode or ingredient name
                <input type="text" name="code" maxlength="40" required autofocus placeholder="For example, ING-0001">
            </label>
            <label class="admin-field">Quantity received
                <input type="number" name="quantity" min="0.001" step="0.001" required>
            </label>
            <div class="admin-recipe-form-action"><button type="submit" class="btn-red">Receive stock</button></div>
        </form>
        <p class="admin-muted">Use an ingredient code such as ING-0001 or enter its exact name. The stock ledger is updated automatically.</p>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Available ingredient codes</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Barcode</th><th>Ingredient</th><th>Current stock</th><th>Minimum stock</th></tr></thead>
                <tbody>
                    @forelse($ingredients as $ingredient)
                        <tr>
                            <td><code>ING-{{ str_pad((string) $ingredient->id, 4, '0', STR_PAD_LEFT) }}</code></td>
                            <td class="admin-strong">{{ $ingredient->name }}</td>
                            <td>{{ $ingredient->current_stock }} {{ $ingredient->unit }}</td>
                            <td>{{ $ingredient->min_threshold }} {{ $ingredient->unit }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="admin-muted">Register ingredients in Inventory management before receiving stock.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
