@extends('layouts.pos')

@section('title', 'Transactions')

@section('content')
    <div class="pos-page-heading">
        <div>
            <p class="pos-eyebrow">Cashier · Order records</p>
            <h1>Transaction ledger</h1>
            <p>Payments and order status remain attached to the same order record.</p>
        </div>
    </div>

    <form method="GET" class="pos-filter-form">
        <div>
            <label for="from">From</label>
            <input id="from" name="from" type="date" value="{{ request('from') }}">
        </div>
        <div>
            <label for="to">To</label>
            <input id="to" name="to" type="date" value="{{ request('to') }}">
        </div>
        <div>
            <label for="status">Order status</label>
            <select id="status" name="status">
                <option value="">All</option>
                @foreach (['pending', 'preparing', 'ready', 'completed', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="q">Order reference</label>
            <input id="q" name="q" value="{{ request('q') }}" maxlength="80">
        </div>
        <button type="submit" class="pos-button pos-button-primary">Apply filters</button>
    </form>

    <div class="pos-table-wrap">
        <table class="pos-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Created</th>
                    <th>Service</th>
                    <th>Order status</th>
                    <th>Payment</th>
                    <th class="is-numeric">Subtotal</th>
                    <th class="is-numeric">VAT exempt</th>
                    <th class="is-numeric">Discount</th>
                    <th class="is-numeric">Total</th>
                    <th>Receipt</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php
                        $subtotal = (float) ($order->subtotal ?? $order->items->sum(fn ($item) => (float) $item->price * (int) $item->quantity));
                        $serviceType = $order->type === 'dine_in'
                            ? 'Dine-in'.($order->table_number ? ' · Table '.$order->table_number : '')
                            : 'Take-out';
                        $vatExclusive = in_array($order->discount_type, ['SC', 'PWD'], true)
                            ? round($subtotal / 1.12, 2)
                            : $subtotal;
                        $vatExemption = in_array($order->discount_type, ['SC', 'PWD'], true)
                            ? round($subtotal - $vatExclusive, 2)
                            : 0;
                    @endphp
                    <tr>
                        <td><strong>{{ $order->reference_number }}</strong></td>
                        <td>{{ $order->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $serviceType }}</td>
                        <td><span class="pos-badge">{{ str($order->status)->headline() }}</span></td>
                        <td>
                            <span class="pos-badge {{ $order->isPaid() ? 'is-paid' : 'is-unpaid' }}">{{ str($order->payment_status)->headline() }}</span>
                            @if ($order->payment_method)
                                <small class="pos-table-subtext">{{ strtoupper($order->payment_method) }}</small>
                            @endif
                        </td>
                        <td class="is-numeric">₱{{ number_format($subtotal, 2) }}</td>
                        <td class="is-numeric">₱{{ number_format($vatExemption, 2) }}</td>
                        <td class="is-numeric">₱{{ number_format((float) $order->discount_amount, 2) }}</td>
                        <td class="is-numeric"><strong>₱{{ number_format((float) $order->total_amount, 2) }}</strong></td>
                        <td><a class="pos-text-link" href="{{ route('staff.pos.receipt', $order) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="pos-table-empty">No order records match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pos-pagination">{{ $orders->withQueryString()->links() }}</div>
@endsection
