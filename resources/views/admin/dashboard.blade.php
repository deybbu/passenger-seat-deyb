@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    {{-- Banner --}}
    <div class="admin-hero-banner">
        <div>
            <h2 class="admin-banner-heading">Good day, Administrator</h2>
            <p class="admin-banner-sub">Here's an overview of your restaurant operations.</p>
        </div>
        <div class="admin-banner-btns">
            <a href="{{ route('admin.orders') }}" class="btn-red">Manage orders</a>
            <a href="{{ route('admin.inventory') }}" class="btn-outline">Receive stock</a>
            <a href="{{ route('admin.menu-management') }}" class="btn-outline">Add menu item</a>
        </div>
    </div>

    <div class="admin-preview-strip">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        <span>Live overview of current orders and ingredient inventory.</span>
    </div>

    {{-- 4 Stat Cards --}}
    <div class="admin-stats-row">
        {{-- Today's sales --}}
        <div class="admin-stat-card">
            <div class="admin-stat-top">
                <span class="admin-stat-title">Today's sales</span>
                <div class="admin-stat-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/>
                        <rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </div>
            </div>
            <div class="admin-stat-number">₱{{ number_format((float) $todaySales, 0) }}</div>
            <div class="admin-stat-footnote">Today's order value</div>
        </div>

        {{-- Total orders --}}
        <div class="admin-stat-card">
            <div class="admin-stat-top">
                <span class="admin-stat-title">Total orders</span>
                <div class="admin-stat-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/>
                        <line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/>
                        <line x1="3" y1="12" x2="3.01" y2="12"/>
                        <line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                </div>
            </div>
            <div class="admin-stat-number">{{ $totalOrders }}</div>
            <div class="admin-stat-footnote">Today's count</div>
        </div>

        {{-- Preparing --}}
        <div class="admin-stat-card">
            <div class="admin-stat-top">
                <span class="admin-stat-title">Preparing</span>
                <div class="admin-stat-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
            </div>
            <div class="admin-stat-number">{{ $preparingOrders }}</div>
            <div class="admin-stat-footnote">Active kitchen orders</div>
        </div>

        {{-- Low stock --}}
        <div class="admin-stat-card">
            <div class="admin-stat-top">
                <span class="admin-stat-title">Low stock</span>
                <div class="admin-stat-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                </div>
            </div>
            <div class="admin-stat-number">{{ $lowStockCount }}</div>
            <div class="admin-stat-footnote">Need review</div>
        </div>
    </div>

    {{-- Mid Row: Sales overview & Low-stock alerts --}}
    <div class="admin-mid-row">
        {{-- Sales Overview Chart --}}
        <div class="admin-card">
            <div class="admin-card-header">
                <span class="admin-card-title">Sales overview</span>
                <div class="admin-pill-group">
                    <a href="{{ route('admin.dashboard', ['range' => 7]) }}" class="admin-pill-btn {{ $range === 7 ? 'active' : '' }}" @if($range === 7) aria-current="true" @endif>7 days</a>
                    <a href="{{ route('admin.dashboard', ['range' => 30]) }}" class="admin-pill-btn {{ $range === 30 ? 'active' : '' }}" @if($range === 30) aria-current="true" @endif>30 days</a>
                </div>
            </div>

            @if(collect($salesChart)->sum('total') > 0)
                <div class="admin-bar-chart {{ $range === 30 ? 'long-range' : '' }}">
                    @foreach($salesChart as $day)
                        @php
                            $barH = $maxSale > 0 ? max(4, min(170, round(($day['total'] / $maxSale) * 165))) : 0;
                        @endphp
                        <div class="admin-chart-col">
                            <div class="admin-bar" style="height: {{ $barH }}px;" title="{{ $day['title'] }} · ₱{{ number_format($day['total'], 0) }}"></div>
                            <span class="admin-chart-day">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="admin-empty-desc">No sales recorded in the selected period.</p>
            @endif
        </div>

        {{-- Low-stock alerts --}}
        <div class="admin-card">
            <div class="admin-card-header">
                <span class="admin-card-title">Low-stock alerts</span>
                <a href="{{ route('admin.inventory') }}" class="btn-outline" style="padding:4px 12px;font-size:11px;">View all</a>
            </div>

            @if($lowStockItems->isNotEmpty())
                @foreach($lowStockItems as $item)
                    @php
                        $cur = (float) $item->current_stock;
                        $min = (float) $item->min_threshold;
                        $pct = $min > 0 ? min(100, max(5, round(($cur / $min) * 100))) : 50;
                    @endphp
                    <div class="admin-low-stock-item">
                        <div class="admin-low-stock-top">
                            <span class="admin-ingredient-name">{{ $item->name }}</span>
                            <span class="badge-status badge-low-stock">Low</span>
                        </div>
                        <div class="admin-progress-bg">
                            <div class="admin-progress-bar" style="width: {{ $pct }}%;"></div>
                        </div>
                        <span class="admin-low-stock-sub">{{ rtrim(rtrim(number_format($cur, 1), '0'), '.') }} {{ $item->unit }} - min {{ rtrim(rtrim(number_format($min, 1), '0'), '.') }} {{ $item->unit }}</span>
                    </div>
                @endforeach
            @else
                <p class="admin-muted">No ingredients need attention.</p>
            @endif
        </div>
    </div>

    {{-- Bottom Row: Recent Orders --}}
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Recent orders</span>
            <a href="{{ route('admin.orders') }}" class="btn-outline" style="padding:4px 12px;font-size:11px;">All orders</a>
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
                    @if($recentOrders->isNotEmpty())
                        @foreach($recentOrders as $order)
                            @php
                                $itemsSummary = $order->items->map(fn($i) => $i->item_name . ' ×' . $i->quantity)->join(', ');
                                $statusClass = match($order->status) {
                                    'pending' => 'badge-pending',
                                    'preparing' => 'badge-preparing',
                                    'ready' => 'badge-ready',
                                    'completed' => 'badge-completed',
                                    default => 'badge-pending',
                                };
                            @endphp
                            <tr>
                                <td class="admin-strong">{{ $order->reference_number }}</td>
                                <td>{{ $order->type === 'dine_in' ? 'Dine-in · T' . ($order->table_number ?? '1') : 'Take-out' }}</td>
                                <td>{{ $itemsSummary ?: '—' }}</td>
                                <td style="font-weight:500;">₱{{ number_format((float) $order->total_amount, 0) }}</td>
                                <td><span class="badge-status {{ $statusClass }}">{{ ucfirst($order->status) }}</span></td>
                            </tr>
                        @endforeach
                    @else
                        <tr><td colspan="5" class="admin-muted">No orders yet.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection
