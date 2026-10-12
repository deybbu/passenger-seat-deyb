@extends('layouts.pos')

@section('title', 'Receipt '.$order->reference_number)

@section('content')
    @php
        $subtotal = (float) ($order->subtotal ?? $order->items->sum(fn ($item) => (float) $item->price * (int) $item->quantity));
        $isVatExemptDiscount = in_array($order->discount_type, ['SC', 'PWD'], true);
        $vatExclusive = $isVatExemptDiscount ? round($subtotal / 1.12, 2) : $subtotal;
        $vatExemption = $isVatExemptDiscount ? round($subtotal - $vatExclusive, 2) : 0;
        $vatIncluded = $isVatExemptDiscount ? 0 : round((float) $order->total_amount * 12 / 112, 2);
        $cashChange = max(0, (float) $order->amount_tendered - (float) $order->total_amount);
        $discountLabel = match ($order->discount_type) {
            'SC' => 'Senior Citizen (20%)',
            'PWD' => 'PWD (20%)',
            'CUSTOM' => 'Promo / custom ('.number_format((float) $order->discount_rate, 2).'%)',
            default => 'None',
        };
    @endphp

    <div class="pos-page-heading pos-receipt-heading">
        <div>
            <p class="pos-eyebrow">Cashier · Sales slip</p>
            <h1>Receipt</h1>
            <p>{{ $order->reference_number }} · {{ $order->isPaid() ? 'Payment recorded' : 'Unpaid order' }}</p>
        </div>
        <div class="pos-actions">
            <button type="button" class="pos-button pos-button-primary" data-print-receipt>Print receipt</button>
            <a class="pos-button" href="{{ route('staff.pos.index') }}">Back to billing</a>
        </div>
    </div>

    <article class="pos-receipt">
        <header class="pos-receipt-header">
            <h2>Passenger Seat</h2>
            <p>Restaurant · Sales slip</p>
        </header>
        <hr>
        <div class="pos-receipt-row"><span>Reference</span><strong>{{ $order->reference_number }}</strong></div>
        <div class="pos-receipt-row"><span>Date</span><span>{{ ($order->paid_at ?? $order->created_at)->format('Y-m-d H:i') }}</span></div>
        <div class="pos-receipt-row"><span>Service</span><span>{{ $order->type === 'dine_in' ? 'Dine-in'.($order->table_number ? ' / Table '.$order->table_number : '') : 'Take-out' }}</span></div>
        @if ($order->customer_name)
            <div class="pos-receipt-row"><span>Customer</span><span>{{ $order->customer_name }}</span></div>
        @endif
        @if ($order->creator)
            <div class="pos-receipt-row"><span>Cashier</span><span>{{ $order->creator->name }}</span></div>
        @endif
        <hr>

        @foreach ($order->items as $item)
            <div class="pos-receipt-row">
                <span>{{ $item->quantity }} × {{ $item->item_name }}<small>₱{{ number_format((float) $item->price, 2) }} each</small></span>
                <span>₱{{ number_format((float) $item->price * $item->quantity, 2) }}</span>
            </div>
        @endforeach

        <hr>
        <div class="pos-receipt-row"><span>Subtotal</span><span>₱{{ number_format($subtotal, 2) }}</span></div>
        @if ($vatExemption > 0)
            <div class="pos-receipt-row"><span>VAT exemption</span><span>−₱{{ number_format($vatExemption, 2) }}</span></div>
        @endif
        @if ($order->discount_type)
            <div class="pos-receipt-row"><span>Discount · {{ $discountLabel }}</span><span>−₱{{ number_format((float) $order->discount_amount, 2) }}</span></div>
        @else
            <div class="pos-receipt-row"><span>Discount</span><span>₱0.00</span></div>
        @endif
        <div class="pos-receipt-row"><span>VAT (included)</span><span>₱{{ number_format($vatIncluded, 2) }}</span></div>
        <div class="pos-receipt-row pos-receipt-total"><strong>TOTAL</strong><strong>₱{{ number_format((float) $order->total_amount, 2) }}</strong></div>
        <hr>

        <div class="pos-receipt-row"><span>Payment status</span><strong>{{ str($order->payment_status)->headline() }}</strong></div>
        @if ($order->isPaid())
            <div class="pos-receipt-row"><span>Payment method</span><span>{{ strtoupper($order->payment_method) }}</span></div>
            @if ($order->payment_method === 'cash')
                <div class="pos-receipt-row"><span>Cash tendered</span><span>₱{{ number_format((float) $order->amount_tendered, 2) }}</span></div>
                <div class="pos-receipt-row"><span>Change</span><span>₱{{ number_format($cashChange, 2) }}</span></div>
            @endif
        @endif
        @if (in_array($order->discount_type, ['SC', 'PWD'], true))
            <hr>
            <div class="pos-receipt-row"><span>Cardholder</span><span>{{ $order->discount_cardholder_name }}</span></div>
            <div class="pos-receipt-row"><span>ID number</span><span>{{ $order->discount_id_number }}</span></div>
            @if ($order->discount_tin)
                <div class="pos-receipt-row"><span>TIN</span><span>{{ $order->discount_tin }}</span></div>
            @endif
        @endif
        @if ($order->special_instructions)
            <hr>
            <p class="pos-receipt-note">Order note: {{ $order->special_instructions }}</p>
        @endif
        <hr>
        <p class="pos-receipt-thanks">Thank you for dining with us!</p>
        <p class="pos-receipt-disclaimer">This is a sales slip, not a BIR official receipt.</p>
    </article>
@endsection
