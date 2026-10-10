@extends('layouts.app')

@section('title', 'Order ' . $order->reference_number)

@section('content')
    <div class="mx-auto max-w-xl">
        <div class="mb-4 rounded-xl bg-brand-600 p-4 text-center text-white">
            <p class="font-display text-3xl font-bold uppercase leading-none">Proceed to the counter for payment</p>
            <p class="mt-1 text-sm text-brand-100">Give the cashier your reference number below.</p>
        </div>

        <section class="pass">
            <div class="p-5 text-center">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Reference number</p>
                <p class="mt-1 break-all font-display text-5xl font-bold tracking-wide text-brand-700">{{ $order->reference_number }}</p>
            </div>

            <div class="pass-divider"></div>

            <dl class="grid grid-cols-2 gap-4 p-5 text-sm">
                <div><dt class="text-xs font-semibold uppercase text-gray-500">Passenger</dt><dd class="font-semibold">{{ $order->customer_name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-gray-500">Service</dt><dd class="font-semibold">{{ $order->type_label }}@if ($order->table_number), Table {{ $order->table_number }}@endif</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-gray-500">Status</dt><dd><x-status-badge :status="$order->status" /></dd></div>
                <div><dt class="text-xs font-semibold uppercase text-gray-500">Payment</dt><dd><x-status-badge :status="$order->payment_status" /></dd></div>
            </dl>

            <div class="pass-divider"></div>

            <ul class="divide-y divide-gray-100 px-5">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3 py-2 text-sm">
                        <span>{{ $item->quantity }} × {{ $item->name }}</span>
                        <span class="font-semibold">₱{{ number_format($item->line_total, 2) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="flex items-center justify-between px-5 py-4">
                <span class="font-display text-2xl font-semibold uppercase">Total</span>
                <span class="font-display text-3xl font-bold text-brand-700">₱{{ number_format((float) $order->total_amount, 2) }}</span>
            </div>

            @if ($order->special_instructions)
                <p class="border-t border-gray-100 px-5 py-3 text-sm"><span class="font-semibold">Your instructions:</span> {{ $order->special_instructions }}</p>
            @endif
        </section>

        <div class="mt-5 flex flex-wrap justify-center gap-3">
            <a href="{{ route('menu.index') }}" class="rounded-full bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">Back to menu</a>
        </div>
    </div>
@endsection
