@extends('layouts.admin')

@section('title', 'POS & transactions')
@section('page-title', 'POS & transactions')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Paid transactions</span>
        </div>

        @if($orders->isEmpty())
            <div class="admin-empty-state">
                <div class="admin-empty-icon-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <line x1="2" y1="10" x2="22" y2="10"/>
                    </svg>
                </div>
                <h3 class="admin-empty-title">POS & transactions is empty</h3>
                <p class="admin-empty-desc">Paid tickets will appear here. Create an order to get started.</p>
                <a href="{{ route('admin.orders') }}" class="btn-red">View order management</a>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-data-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Method</th>
                            <th>Total</th>
                            <th>Paid at</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td class="admin-strong">{{ $order->reference_number }}</td>
                                <td>{{ strtoupper($order->payment_method ?? '—') }}</td>
                                <td>₱{{ number_format((float) $order->total_amount, 0) }}</td>
                                <td>{{ optional($order->paid_at ?? $order->updated_at)->format('M j, g:i A') }}</td>
                                <td><span class="badge-status badge-completed">{{ ucfirst($order->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())
                <div style="padding:16px 8px 0;display:flex;justify-content:center;">{{ $orders->links() }}</div>
            @endif
        @endif
    </div>
@endsection
