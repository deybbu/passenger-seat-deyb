@props(['order'])
{{-- 80mm thermal receipt. Pair with the print styles on the receipt page. --}}
<div class="receipt mx-auto bg-white p-3 font-mono text-[12px] leading-snug text-black" style="width: 80mm;">
    <div class="text-center">
        <p class="text-base font-bold tracking-wide">PASSENGER SEAT</p>
        <p>Restaurant</p>
        <p>--------------------------------</p>
    </div>

    <p>Ref: <strong>{{ $order->reference_number }}</strong></p>
    <p>Date: {{ ($order->paid_at ?? $order->created_at)->format('M d, Y g:i A') }}</p>
    <p>{{ $order->type_label }}@if ($order->table_number), Table {{ $order->table_number }}@endif</p>
    <p>Customer: {{ $order->customer_name }}</p>
    @if ($order->cashier)<p>Cashier: {{ $order->cashier->name }}</p>@endif
    <p>--------------------------------</p>

    @foreach ($order->items as $item)
        <div class="flex justify-between gap-2">
            <span>{{ $item->quantity }} x {{ $item->name }}</span>
            <span class="shrink-0">{{ number_format($item->line_total, 2) }}</span>
        </div>
        <div class="pl-3 text-[11px]">@ {{ number_format((float) $item->price, 2) }}</div>
    @endforeach

    <p>--------------------------------</p>
    <div class="flex justify-between text-sm font-bold">
        <span>TOTAL</span>
        <span>PHP {{ number_format((float) $order->total_amount, 2) }}</span>
    </div>

    @if ($order->isPaid())
        <p class="mt-1">Paid by: {{ strtoupper($order->payment_method) }}</p>
        @if ($order->payment_method === 'cash')
            <div class="flex justify-between"><span>Cash</span><span>{{ number_format((float) $order->amount_tendered, 2) }}</span></div>
            <div class="flex justify-between"><span>Change</span><span>{{ number_format($order->change, 2) }}</span></div>
        @endif
        @if ($order->payment_reference)<p>Ref no: {{ $order->payment_reference }}</p>@endif
    @else
        <p class="mt-1 text-center font-bold">** UNPAID **</p>
    @endif

    @if ($order->special_instructions)
        <p class="mt-1">Note: {{ $order->special_instructions }}</p>
    @endif

    <p>--------------------------------</p>
    <p class="text-center">Thank you for flying with us!</p>
    <p class="text-center text-[10px]">This is a sales slip, not a BIR official receipt.</p>
</div>
