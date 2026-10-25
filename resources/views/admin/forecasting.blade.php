@extends('layouts.admin')

@section('title', 'Predictive forecasting')
@section('page-title', 'Predictive forecasting')

@section('content')
    <div class="admin-stats-row">
        <div class="admin-stat-card"><span class="admin-stat-title">Average daily orders</span><strong class="admin-stat-number">{{ number_format($avgOrders, 1) }}</strong></div>
        <div class="admin-stat-card"><span class="admin-stat-title">Average daily sales</span><strong class="admin-stat-number">₱{{ number_format($avgSales, 2) }}</strong></div>
        <div class="admin-stat-card"><span class="admin-stat-title">Low-stock ingredients</span><strong class="admin-stat-number">{{ $lowStock->count() }}</strong></div>
    </div>
    <div class="admin-card" style="margin:16px 0;">
        <div class="admin-card-header"><span class="admin-card-title">Next 7 days · baseline forecast</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Day</th><th>Estimated orders</th><th>Estimated sales</th></tr></thead>
                <tbody>
                    @foreach($forecast as $day)
                        <tr><td class="admin-strong">{{ $day['label'] }}</td><td>{{ $day['orders'] }}</td><td>₱{{ number_format($day['sales'], 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Stock to review</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Ingredient</th><th>Current stock</th><th>Minimum</th></tr></thead>
                <tbody>
                    @forelse($lowStock as $ingredient)
                        <tr><td class="admin-strong">{{ $ingredient->name }}</td><td>{{ number_format($ingredient->usableStock(), 3) }} {{ $ingredient->unit }} usable</td><td>{{ $ingredient->min_threshold }} {{ $ingredient->unit }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="admin-muted">No low-stock ingredients.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
