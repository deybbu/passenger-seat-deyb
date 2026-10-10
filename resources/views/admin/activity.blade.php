@extends('layouts.admin')

@section('title', 'Activity logs')
@section('page-title', 'Activity logs')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Recent inventory activity</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Time</th><th>Ingredient</th><th>Action</th><th>Quantity</th><th>By</th><th>Notes</th></tr></thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('M j, g:i A') }}</td>
                            <td class="admin-strong">{{ $log->ingredient->name ?? 'Deleted ingredient' }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $log->type)) }}</td>
                            <td>{{ $log->quantity }} {{ $log->ingredient->unit ?? '' }}</td>
                            <td>{{ $log->user->name ?? 'System' }}</td>
                            <td>{{ $log->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="admin-muted">No inventory activity has been recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Recently updated orders</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Order</th><th>Status</th><th>Updated</th></tr></thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr><td class="admin-strong"><a href="{{ route('admin.orders', ['status' => $order->status]) }}">{{ $order->reference_number }}</a></td><td>{{ ucfirst($order->status) }}</td><td>{{ $order->updated_at->diffForHumans() }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="admin-muted">No orders have been recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
