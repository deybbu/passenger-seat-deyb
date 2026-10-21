@extends('layouts.admin')

@section('title', 'Analytics & reports')
@section('page-title', 'Analytics & reports')

@section('content')
    <div class="admin-page-actions">
        <div>
            <p class="admin-muted">POS &amp; transactions · revenue and product performance</p>
        </div>
        <div class="admin-inline-actions">
            @foreach(['7D', '30D', 'YEAR'] as $option)
                <a class="btn-outline {{ $rangeLabel === $option ? 'is-active' : '' }}" href="{{ route('admin.analytics', ['range' => $option]) }}">{{ $option }}</a>
            @endforeach
        </div>
    </div>

    <div class="admin-stats-row">
        @foreach([
            ['Total sales revenue ('.$rangeLabel.')', $totalSales === 0 ? '₱0.00' : '₱'.number_format($totalSales, 2)],
            ['Total orders', number_format($totalOrders)],
            ['Avg order value', $averageOrderValue === null ? '—' : '₱'.number_format($averageOrderValue, 2)],
            ['Items per order', $itemsPerOrder === null ? '—' : number_format($itemsPerOrder, 1)],
        ] as $stat)
            <div class="admin-stat-card"><span class="admin-stat-title">{{ $stat[0] }}</span><strong class="admin-stat-number">{{ $stat[1] }}</strong></div>
        @endforeach
    </div>

    <div class="admin-card" style="margin:16px 0;">
        <div class="admin-card-header"><span class="admin-card-title">Revenue trend <span class="badge-status badge-in-stock">{{ $rangeLabel }}</span></span></div>
        <div class="admin-bar-chart" aria-label="Revenue trend">
            @forelse($salesChart as $day)
                <div class="admin-chart-col">
                    <div class="admin-bar" style="height:{{ max(4, (int) round($day['total'] / $maxSale * 100)) }}%" title="{{ $day['label'] }}: ₱{{ number_format($day['total'], 2) }}"></div>
                    <span class="admin-chart-day">{{ $day['label'] }}</span>
                </div>
            @empty
                <p class="admin-muted">No revenue data is available for this period.</p>
            @endforelse
        </div>
    </div>

    <div class="admin-two-column-grid">
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Orders by status</span></div>
            <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Status</th><th>Orders</th></tr></thead><tbody>
                @forelse($byStatus as $status => $count)
                    <tr><td><span class="badge-status badge-{{ in_array($status, ['completed'], true) ? 'in-stock' : ($status === 'cancelled' ? 'out-of-stock' : 'low-stock') }}">{{ ucfirst($status) }}</span></td><td>{{ $count }}</td></tr>
                @empty
                    <tr><td colspan="2" class="admin-muted">No orders in this period.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Sales by category</span></div>
            <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Category</th><th>Sales</th></tr></thead><tbody>
                @forelse($salesByCategory as $category => $total)
                    <tr><td>{{ $category }}</td><td>₱{{ number_format($total, 2) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="admin-muted">No category sales in this period.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>

    <div class="admin-two-column-grid" style="margin-top:16px;">
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Top products</span></div>
            <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Product</th><th>Units sold</th><th>Revenue</th></tr></thead><tbody>
                @forelse($topProducts as $product)
                    <tr><td class="admin-strong">{{ $product['name'] }}</td><td>{{ $product['units'] }}</td><td>₱{{ number_format($product['revenue'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="admin-muted">No product sales in this period.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Inventory status</span></div>
            <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Status</th><th>Ingredients</th></tr></thead><tbody>
                @foreach($inventoryStatus as $status => $count)
                    <tr><td>{{ $status }}</td><td>{{ $count }}</td></tr>
                @endforeach
            </tbody></table></div>
        </div>
    </div>

    <div class="admin-two-column-grid" style="margin-top:16px;">
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">By payment method</span></div>
            <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Method</th><th>Revenue</th></tr></thead><tbody>
                @forelse($paymentMethods as $payment)
                    <tr><td>{{ $payment->method }}</td><td>₱{{ number_format((float) $payment->total, 2) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="admin-muted">No payment data in this period.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Revenue by day</span></div>
            <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Date</th><th>Revenue</th></tr></thead><tbody>
                @forelse($salesChart as $day)
                    <tr><td>{{ $day['label'] }}</td><td>₱{{ number_format($day['total'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="admin-muted">No revenue data.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>
@endsection
