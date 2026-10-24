@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="admin-dashboard-hero hero">
        <div>
            <h2>Good day, {{ $administratorName }}</h2>
            <span class="admin-muted mu">Overview of your restaurant operations.</span>
        </div>
        <div class="admin-dashboard-actions row">
            <div class="admin-dashboard-range tabs" role="group" aria-label="Revenue period">
                @foreach([
                    ['key' => 7, 'label' => '7D'],
                    ['key' => 30, 'label' => '30D'],
                    ['key' => 'year', 'label' => 'YEAR'],
                ] as $period)
                    <a href="{{ route('admin.dashboard', ['range' => $period['label']]) }}"
                       class="{{ $range === $period['key'] ? 'on' : '' }}"
                       @if($range === $period['key']) aria-current="true" @endif>
                        {{ $period['label'] }}
                    </a>
                @endforeach
            </div>
            <a href="{{ route('admin.orders') }}" class="btn-red">+ Create order</a>
            <a href="{{ route('admin.barcode') }}" class="btn-outline">Receive stock</a>
            <a href="{{ route('admin.menu-management') }}" class="btn-outline">Add menu item</a>
        </div>
    </div>

    <div class="admin-dashboard-stats g">
        <article class="admin-stat-card card stat">
            <span class="admin-stat-title">Revenue ({{ $rangeLabel }})</span>
            <strong class="admin-stat-number">₱{{ number_format($revenue, 2) }}</strong>
            <span class="admin-stat-footnote">{{ $rangeDescription }}</span>
        </article>
        <article class="admin-stat-card card stat">
            <span class="admin-stat-title">Orders ({{ $rangeLabel }})</span>
            <strong class="admin-stat-number">{{ number_format($periodOrderCount) }}</strong>
            <span class="admin-stat-footnote">{{ $rangeDescription }}</span>
        </article>
        <article class="admin-stat-card card stat">
            <span class="admin-stat-title">Avg order value</span>
            <strong class="admin-stat-number">{{ $averageOrderValue === null ? '—' : '₱'.number_format($averageOrderValue, 2) }}</strong>
            <span class="admin-stat-footnote">Revenue divided by orders</span>
        </article>
        <article class="admin-stat-card card stat">
            <span class="admin-stat-title">Food cost %</span>
            <strong class="admin-stat-number">{{ $foodCostPercent === null ? '—' : number_format($foodCostPercent, 1).'%' }}</strong>
            <span class="admin-stat-footnote">{{ $foodCostMessage }}</span>
        </article>
        <article class="admin-stat-card card stat">
            <span class="admin-stat-title">Today's sales</span>
            <strong class="admin-stat-number">₱{{ number_format($todaySales, 2) }}</strong>
            <span class="admin-stat-footnote">Orders today, excluding cancelled</span>
        </article>
        <article class="admin-stat-card card stat">
            <span class="admin-stat-title">Preparing</span>
            <strong class="admin-stat-number">{{ number_format($preparingOrders) }}</strong>
            <span class="admin-stat-footnote">Active kitchen orders</span>
        </article>
        <article class="admin-stat-card card stat">
            <span class="admin-stat-title">Low stock</span>
            <strong class="admin-stat-number">{{ number_format($lowStockCount) }}</strong>
            <span class="admin-stat-footnote">Ingredients needing review</span>
        </article>
        <article class="admin-stat-card card stat">
            <span class="admin-stat-title">Waste cost</span>
            <strong class="admin-stat-number">{{ $wasteCost === null ? '—' : '₱'.number_format($wasteCost, 2) }}</strong>
            <span class="admin-stat-footnote">{{ $wasteCost === null ? 'Cost data incomplete' : $rangeDescription }}</span>
        </article>
    </div>

    <div class="admin-dashboard-grid admin-dashboard-grid-top g2 mt">
        <section class="admin-card card" aria-labelledby="adminRevenueChartTitle">
            <div class="admin-card-header">
                <h3 class="admin-card-title" id="adminRevenueChartTitle">Revenue trend</h3>
                <span class="badge-status badge-in-stock">{{ $rangeLabel }}</span>
            </div>
            <div class="admin-chart-legend-row">
                <button type="button" class="admin-chart-legend" data-dashboard-series-toggle
                        data-chart-target="adminRevenueChart" aria-pressed="true">
                    <span class="admin-chart-legend-mark" aria-hidden="true"></span>
                    Revenue (₱)
                </button>
                <span class="admin-muted mu">Click legend to show or hide · Hover for values</span>
            </div>

            @if(collect($salesChart)->sum('total') > 0)
                @php
                    $chartWidth = 560;
                    $chartHeight = 240;
                    $chartLeft = 70;
                    $chartRight = 14;
                    $chartTop = 12;
                    $chartBottom = 48;
                    $chartPlotWidth = $chartWidth - $chartLeft - $chartRight;
                    $chartPlotHeight = $chartHeight - $chartTop - $chartBottom;
                    $chartScale = max($maxSale * 1.1, 1);
                    $chartCount = count($salesChart);
                    $chartPoints = [];
                    foreach ($salesChart as $index => $day) {
                        $x = $chartLeft + ($chartCount > 1
                            ? $index * $chartPlotWidth / ($chartCount - 1)
                            : $chartPlotWidth / 2);
                        $y = $chartTop + $chartPlotHeight * (1 - $day['total'] / $chartScale);
                        $chartPoints[] = ['x' => round($x, 2), 'y' => round($y, 2), 'day' => $day];
                    }
                    $chartPointString = collect($chartPoints)
                        ->map(fn ($point) => $point['x'].','.$point['y'])
                        ->implode(' ');
                    $chartLabelStep = max(1, (int) ceil($chartCount / 8));
                @endphp
                <div class="admin-chart-scroll">
                    <svg id="adminRevenueChart" class="admin-revenue-chart" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}"
                         role="img" aria-label="Revenue in pesos by {{ $range === 'year' ? 'month for the last 12 months' : 'date' }}">
                        @for($tick = 0; $tick <= 4; $tick++)
                            @php
                                $tickValue = $chartScale * (4 - $tick) / 4;
                                $tickY = $chartTop + $chartPlotHeight * $tick / 4;
                            @endphp
                            <line x1="{{ $chartLeft }}" x2="{{ $chartWidth - $chartRight }}"
                                  y1="{{ $tickY }}" y2="{{ $tickY }}" class="admin-chart-gridline" />
                            <text x="{{ $chartLeft - 7 }}" y="{{ $tickY + 4 }}" text-anchor="end"
                                  class="admin-chart-axis-label">₱{{ number_format($tickValue, 0) }}</text>
                        @endfor
                        @foreach($chartPoints as $index => $point)
                            @if($index % $chartLabelStep === 0)
                                <text x="{{ $point['x'] }}" y="{{ $chartHeight - $chartBottom + 14 }}"
                                      text-anchor="middle" class="admin-chart-axis-label">{{ $point['day']['label'] }}</text>
                            @endif
                        @endforeach
                        <g data-chart-series="revenue">
                            <polygon points="{{ $chartPointString }} {{ $chartPoints[array_key_last($chartPoints)]['x'] }},{{ $chartTop + $chartPlotHeight }} {{ $chartPoints[0]['x'] }},{{ $chartTop + $chartPlotHeight }}"
                                     class="admin-chart-area" />
                            <polyline points="{{ $chartPointString }}" class="admin-chart-line" />
                            @foreach($chartPoints as $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" class="admin-chart-point">
                                    <title>{{ $point['day']['title'] }}: ₱{{ number_format($point['day']['total'], 2) }}</title>
                                </circle>
                            @endforeach
                        </g>
                        <text x="{{ ($chartLeft + $chartWidth - $chartRight) / 2 }}" y="{{ $chartHeight - 6 }}"
                              text-anchor="middle" class="admin-chart-axis-title">
                            {{ $range === 'year' ? 'Month (last 12 months)' : 'Date (MM-DD)' }}
                        </text>
                        <text transform="rotate(-90)" x="{{ -($chartTop + $chartHeight - $chartBottom) / 2 }}" y="13"
                              text-anchor="middle" class="admin-chart-axis-title">Revenue (₱)</text>
                    </svg>
                </div>
            @else
                <div class="admin-dashboard-empty" role="status">No revenue recorded in this period.</div>
            @endif
        </section>

        <section class="admin-card card" aria-labelledby="adminOrdersByStatusTitle">
            <div class="admin-card-header">
                <h3 class="admin-card-title" id="adminOrdersByStatusTitle">Orders by status</h3>
            </div>
            @php
                $donutTotal = $orderStatusCounts->sum();
                $donutRadius = 56;
                $donutCircumference = 2 * pi() * $donutRadius;
                $donutOffset = 0;
            @endphp
            @if($donutTotal > 0)
                <div class="admin-donut-layout">
                    <svg class="admin-status-donut" viewBox="0 0 160 160" role="img"
                         aria-label="Orders by status, {{ $donutTotal }} total orders">
                        <circle r="{{ $donutRadius }}" cx="80" cy="80" class="admin-donut-track" />
                        @foreach($orderStatusCounts as $status => $count)
                            @php
                                $segmentLength = $count / $donutTotal * $donutCircumference;
                                $segmentOffset = $donutOffset;
                                $donutOffset += $segmentLength;
                            @endphp
                            @if($count > 0)
                                <circle r="{{ $donutRadius }}" cx="80" cy="80"
                                        class="admin-donut-segment admin-donut-{{ $status }}"
                                        stroke-dasharray="{{ $segmentLength }} {{ $donutCircumference - $segmentLength }}"
                                        stroke-dashoffset="{{ -$segmentOffset }}"
                                        transform="rotate(-90 80 80)">
                                    <title>{{ ucfirst($status) }}: {{ $count }} orders ({{ round($count / $donutTotal * 100) }}%)</title>
                                </circle>
                            @endif
                        @endforeach
                        <text x="80" y="76" text-anchor="middle" class="admin-donut-total">{{ number_format($donutTotal) }}</text>
                        <text x="80" y="96" text-anchor="middle" class="admin-donut-caption">orders</text>
                    </svg>
                    <ul class="admin-donut-legend">
                        @foreach($orderStatusCounts as $status => $count)
                            <li>
                                <span class="admin-donut-swatch admin-donut-{{ $status }}" aria-hidden="true"></span>
                                <span>{{ ucfirst($status) }}</span>
                                <strong>{{ number_format($count) }}</strong>
                                <small>{{ round($count / $donutTotal * 100) }}%</small>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="admin-dashboard-empty" role="status">No orders recorded yet.</div>
            @endif
        </section>
    </div>

    <div class="admin-dashboard-grid admin-dashboard-grid-bottom g2 mt">
        <section class="admin-card card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Recent orders</h3>
                <a href="{{ route('admin.orders') }}" class="btn-outline">All orders</a>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Type</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            @php
                                $itemsSummary = $order->items
                                    ->map(fn ($item) => $item->item_name.' ×'.$item->quantity)
                                    ->join(', ');
                                $statusClass = match ($order->status) {
                                    'pending' => 'badge-pending',
                                    'preparing' => 'badge-preparing',
                                    'ready' => 'badge-ready',
                                    'served' => 'badge-completed',
                                    'cancelled' => 'badge-cancelled',
                                    default => 'badge-pending',
                                };
                            @endphp
                            <tr>
                                <td class="admin-strong">#{{ $order->reference_number }}</td>
                                <td>{{ $order->type === 'dine_in' ? 'Dine-in · T'.($order->table_number ?? '1') : 'Take-out' }}</td>
                                <td>{{ $itemsSummary ?: '—' }}</td>
                                <td>₱{{ number_format((float) $order->total_amount, 2) }}</td>
                                <td><span class="badge-status {{ $statusClass }}">{{ ucfirst($order->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="admin-muted">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-card card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Low-stock alerts</h3>
                <a href="{{ route('admin.inventory') }}" class="btn-outline">View all</a>
            </div>
            @forelse($lowStockItems as $item)
                @php
                    $currentStock = $item->usableStock();
                    $minimumStock = (float) $item->min_threshold;
                    $stockPercent = $minimumStock > 0
                        ? min(100, max(0, round($currentStock / $minimumStock * 100)))
                        : 0;
                    $stockBadge = $currentStock <= 0 ? 'badge-out-of-stock' : 'badge-low-stock';
                    $stockLabel = $currentStock <= 0 ? 'Out of stock' : 'Low stock';
                @endphp
                <div class="admin-low-stock-item">
                    <div class="admin-low-stock-top">
                        <span class="admin-ingredient-name">{{ $item->name }}</span>
                        <span class="badge-status {{ $stockBadge }}">{{ $stockLabel }}</span>
                    </div>
                    <div class="admin-progress-bg" role="progressbar"
                         aria-label="{{ $item->name }} stock level"
                         aria-valuemin="0" aria-valuemax="{{ $minimumStock }}"
                         aria-valuenow="{{ min($currentStock, $minimumStock) }}">
                        <div class="admin-progress-bar" style="width: {{ $stockPercent }}%;"></div>
                    </div>
                    <span class="admin-low-stock-sub">
                        {{ rtrim(rtrim(number_format($currentStock, 3), '0'), '.') }} {{ $item->unit }}
                        · min {{ rtrim(rtrim(number_format($minimumStock, 3), '0'), '.') }} {{ $item->unit }}
                    </span>
                </div>
            @empty
                <div class="admin-dashboard-empty" role="status">All ingredients are stocked.</div>
            @endforelse
        </section>
    </div>
@endsection
