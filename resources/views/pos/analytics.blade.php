@extends('layouts.pos-register')

@section('title', 'Passenger Seat POS · Sales')
@section('pos-tab', 'sales')

@section('content')
    @php
        $yesterdayNote = $yesterdayRevenue > 0
            ? number_format((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1).'% vs yesterday'
            : 'No sales yesterday';
    @endphp
    <h1 style="margin:0;font-size:26px">Analytics &amp; Reports</h1>
    <p class="mute" style="margin:0 0 14px">
        Business insights and product performance. Completed paid orders (last 7 days): {{ $completedOrderCount }}.
        Average paid order: {{ $averageOrderValue === null ? '—' : '₱'.number_format($averageOrderValue, 2) }}.
    </p>

    <div class="ov" aria-label="Today's sales overview">
        <div>
            <span>Total sales revenue (today)</span>
            <b>₱{{ number_format($todayRevenue, 2) }}</b>
            <small>{{ $yesterdayNote }}</small>
        </div>
        <div>
            <span>Total orders (today)</span>
            <b>{{ number_format($todayOrderCount) }}</b>
            <small>{{ number_format($todayPendingOrderCount) }} pending</small>
        </div>
        <div>
            <span>Average order value</span>
            <b>{{ $todayAverageOrderValue === null ? '₱0.00' : '₱'.number_format($todayAverageOrderValue, 2) }}</b>
            <small>Average paid order today</small>
        </div>
        <div>
            <span>Top category</span>
            <b>{{ $todayTopCategory }}</b>
            <small>Highest sales today</small>
        </div>
    </div>

    <div class="charts">
        <section class="card">
            <h2>Weekly revenue trend</h2>
            @if ($completedOrderCount > 0)
                <svg viewBox="0 0 600 220" style="width:100%;height:auto" role="img" aria-label="Weekly revenue trend">
                    <line x1="24" y1="180" x2="578" y2="180" stroke="var(--line)" />
                    <polyline points="{{ $weeklyTrend->pluck('point')->implode(' ') }}" fill="none" stroke="var(--red)" stroke-width="3" />
                    @foreach ($weeklyTrend as $day)
                        <circle cx="{{ $day['x'] }}" cy="{{ $day['y'] }}" r="4" fill="var(--surf)" stroke="var(--red)" stroke-width="2">
                            <title>{{ $day['date'] }}: ₱{{ number_format($day['revenue'], 2) }}</title>
                        </circle>
                        <text x="{{ $day['x'] }}" y="207" text-anchor="middle" font-size="11" fill="var(--mute)">{{ $day['label'] }}</text>
                    @endforeach
                </svg>
                <p class="mute">{{ $rangeStart->format('M j') }}–{{ $rangeEnd->format('M j, Y') }} · ₱{{ number_format($weeklyRevenue, 2) }} total</p>
            @else
                <p class="mute">No completed paid orders in the last 7 days.</p>
            @endif
        </section>

        <section class="card">
            <h2>Sales by category</h2>
            @forelse ($categorySales as $category)
                <div style="margin:10px 0">
                    <div style="display:flex;justify-content:space-between">
                        <strong>{{ $category['name'] }}</strong>
                        <span>₱{{ number_format($category['sales'], 2) }}</span>
                    </div>
                    <progress value="{{ $category['sales'] }}" max="{{ $maxCategorySales }}" aria-label="{{ $category['name'] }} sales" style="width:100%"></progress>
                </div>
            @empty
                <p class="mute">No category sales recorded this week.</p>
            @endforelse
        </section>
    </div>

    <div class="cols" style="margin-top:12px">
        <section class="card">
            <h2>Top items</h2>
            <div class="wrap">
                <table>
                    <thead><tr><th>Item</th><th>Sold</th><th>Sales</th></tr></thead>
                    <tbody>
                        @forelse ($todayTopItems as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ number_format($item['quantity']) }}</td>
                                <td>₱{{ number_format($item['sales'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="mute">No item sales recorded today.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>By payment method</h2>
            <div class="wrap">
                <table>
                    <thead><tr><th>Method</th><th>Orders</th><th>Sales</th></tr></thead>
                    <tbody>
                        @forelse ($todayPaymentSales as $payment)
                            <tr>
                                <td>{{ $payment['method'] }}</td>
                                <td>{{ number_format($payment['orders']) }}</td>
                                <td>₱{{ number_format($payment['sales'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="mute">No completed payments recorded today.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
