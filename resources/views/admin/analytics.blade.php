@extends('layouts.admin')

@section('title', 'Analytics & reports')
@section('page-title', 'Analytics & reports')

@section('content')
    <div class="admin-stats-row">
        <div class="admin-stat-card"><span class="admin-stat-title">Today's sales</span><strong class="admin-stat-number">₱{{ number_format($todaySales, 2) }}</strong></div>
        <div class="admin-stat-card"><span class="admin-stat-title">Sales this week</span><strong class="admin-stat-number">₱{{ number_format($weekSales, 2) }}</strong></div>
        @foreach($byStatus as $status => $count)
            <div class="admin-stat-card"><span class="admin-stat-title">{{ ucfirst($status) }} orders</span><strong class="admin-stat-number">{{ $count }}</strong></div>
        @endforeach
    </div>

    <div class="admin-card" style="margin:16px 0;">
        <div class="admin-card-header"><span class="admin-card-title">Sales by day · last 7 days</span></div>
        <div class="admin-bar-chart" aria-label="Daily sales chart">
            @foreach($salesChart as $day)
                <div class="admin-chart-col">
                    <div class="admin-bar" style="height:{{ max(4, (int) round($day['total'] / $maxSale * 100)) }}%" title="₱{{ number_format($day['total'], 2) }}"></div>
                    <span class="admin-chart-day">{{ $day['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Top selling items</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Menu item</th><th>Quantity sold</th></tr></thead>
                <tbody>
                    @forelse($topItems as $item)
                        <tr><td class="admin-strong">{{ $item->item_name }}</td><td>{{ $item->qty }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="admin-muted">No sales data is available yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
