@extends('layouts.admin')

@section('title', 'Order management')
@section('page-title', 'Order management')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Orders</span>
            <div class="admin-pill-group">
                @foreach(['all' => 'All', 'pending' => 'Pending', 'preparing' => 'Preparing', 'ready' => 'Ready', 'completed' => 'Completed'] as $key => $label)
                    <a href="{{ route('admin.orders', array_filter(['status' => $key, 'q' => $search ?? null])) }}"
                       class="admin-pill-btn {{ ($filter ?? 'all') === $key ? 'active' : '' }}">
                        {{ $label }}
                        <span class="admin-tab-count">{{ $counts[$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
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
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $itemsSummary = $order->items->map(fn($i) => $i->item_name . ' ×' . $i->quantity)->join(', ');
                            $statusClass = match($order->status) {
                                'pending' => 'badge-pending',
                                'preparing' => 'badge-preparing',
                                'ready' => 'badge-ready',
                                'completed' => 'badge-completed',
                                default => 'badge-pending',
                            };
                            $next = $order->nextStatus();
                        @endphp
                        <tr>
                            <td class="admin-strong">{{ $order->reference_number }}</td>
                            <td>{{ $order->type === 'dine_in' ? 'Dine-in · T' . ($order->table_number ?? '1') : 'Take-out' }}</td>
                            <td>{{ $itemsSummary ?: '—' }}</td>
                            <td style="font-weight:500;">₱{{ number_format((float) $order->total_amount, 0) }}</td>
                            <td><span class="badge-status {{ $statusClass }}">{{ ucfirst($order->status) }}</span></td>
                            <td>
                                @if($next)
                                    <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $next }}">
                                        <button type="submit" class="btn-red" style="padding:5px 10px;font-size:11px;">Mark {{ ucfirst($next) }}</button>
                                    </form>
                                @else
                                    <span class="admin-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="admin-muted">No orders in this tab yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($orders, 'hasPages') && $orders->hasPages())
            <div style="padding:16px 8px 0;display:flex;justify-content:center;">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
@endsection
