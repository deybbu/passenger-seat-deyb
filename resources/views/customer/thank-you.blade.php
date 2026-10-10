@extends('layouts.customer')
@section('title', 'Order Confirmed')
@section('content')
<section class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-red-800 px-6 py-10 text-center text-white sm:px-10">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-white text-2xl font-black text-red-800">✓</div>
            <p class="mt-4 text-xs font-bold uppercase tracking-[.24em] text-red-100">Boarding pass issued</p>
            <h1 class="mt-2 text-3xl font-black">Thank you for your order!</h1>
            <p class="mt-3 text-red-50">Your order has been sent to our restaurant team.</p>
        </div>
        <div class="p-6 sm:p-10">
            <div class="rounded-2xl border-2 border-dashed border-red-200 bg-red-50 p-5 text-center">
                <p class="text-xs font-bold uppercase tracking-[.2em] text-red-700">Order reference</p>
                <p class="mt-2 text-3xl font-black tracking-wider text-red-900">{{ $order->reference_number }}</p>
                <p class="mt-2 text-sm text-slate-600">Keep this number for payment and order follow-up.</p>
            </div>
            <div class="mt-7 flex items-center justify-between gap-4"><span class="text-slate-600">Service</span><span class="font-bold">{{ $order->type === 'dine_in' ? 'Dine-in · Table ' . $order->table_number : 'Take-out' }}</span></div>
            <div class="mt-3 flex items-center justify-between gap-4"><span class="text-slate-600">Order status</span><span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-900">{{ ucfirst($order->status) }}</span></div>
            <div class="mt-3 flex items-center justify-between gap-4"><span class="text-slate-600">Payment</span><span class="font-bold text-red-700">Unpaid · Pay at counter</span></div>
            <div class="mt-6 border-t border-slate-200 pt-5">
                @foreach ($order->items as $item)
                    <div class="flex justify-between gap-4 py-2 text-sm"><span>{{ $item->quantity }} × {{ $item->item_name }}</span><span class="font-semibold">₱{{ number_format((float) $item->price * $item->quantity, 2) }}</span></div>
                @endforeach
                <div class="mt-3 flex justify-between border-t border-slate-200 pt-4"><span class="font-bold">Total</span><span class="text-xl font-black text-red-800">₱{{ number_format((float) $order->total_amount, 2) }}</span></div>
            </div>
            @if ($order->special_instructions)
                <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm"><p class="font-bold">Special instructions</p><p class="mt-1 text-slate-600">{{ $order->special_instructions }}</p></div>
            @endif
            <div class="mt-7 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong>Next step:</strong> Proceed to the counter for payment. Our staff will confirm payment and prepare your order.</div>
            <a href="{{ route('menu.index') }}" class="mt-6 inline-flex w-full justify-center rounded-xl bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800">Return to menu</a>
        </div>
    </div>
</section>
@endsection
