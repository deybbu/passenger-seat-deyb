@extends('layouts.pos')

@section('title', 'Transactions')

@section('content')
    <div class="pos-page-heading">
        <div>
            <p class="pos-eyebrow">Cashier · Order records</p>
            <h1>Transaction ledger</h1>
            <p>Order records and table-session payment adjustments are listed below.</p>
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
                @foreach (['pending', 'preparing', 'ready', 'served', 'cancelled'] as $status)
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

    <div class="pos-page-heading" style="margin-top:28px">
        <div>
            <p class="pos-eyebrow">Shared table tabs</p>
            <h2>Table-session payments</h2>
        </div>
    </div>
    <div class="pos-table-wrap">
        <table class="pos-table">
            <thead>
                <tr>
                    <th>Recorded</th>
                    <th>Table</th>
                    <th>Record</th>
                    <th>Payment method</th>
                    <th>Cashier / manager</th>
                    <th class="is-numeric">Amount</th>
                    <th>Audit / action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessionPayments as $payment)
                    <tr>
                        <td>{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                        <td>Table {{ $payment->tableSession?->table_number ?? '—' }}</td>
                        <td>
                            <strong>{{ $payment->payment_reference }}</strong>
                            <small class="pos-table-subtext">{{ str($payment->type)->headline() }}</small>
                        </td>
                        <td>{{ strtoupper($payment->payment_method) }}</td>
                        <td>{{ $payment->performedBy?->name ?? 'Former staff member' }}</td>
                        <td class="is-numeric">
                            <strong>{{ $payment->type === 'reversal' ? '−' : '' }}₱{{ number_format((float) $payment->amount, 2) }}</strong>
                        </td>
                        <td>
                            @if ($payment->type === 'reversal')
                                <span>Reverses {{ $payment->reversalOf?->payment_reference ?? 'payment' }}</span>
                                @if ($payment->notes)
                                    <small class="pos-table-subtext">{{ $payment->notes }}</small>
                                @endif
                            @elseif ($payment->reversal)
                                <span>Reversed by {{ $payment->reversal->payment_reference }}</span>
                            @elseif ($canReopenTableBills && $payment->tableSession?->status === 'closed')
                                <form method="POST" action="{{ route('staff.pos.table-session-reopen', $payment->tableSession) }}">
                                    @csrf
                                    <label for="reopen-reason-{{ $payment->id }}">Reason for reopening</label>
                                    <input id="reopen-reason-{{ $payment->id }}" name="reason" type="text" maxlength="500" required>
                                    <button type="submit" class="pos-text-link">Reopen bill</button>
                                </form>
                            @else
                                <span>—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="pos-table-empty">No table-session payment records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pos-pagination">{{ $sessionPayments->links() }}</div>
@endsection
