@extends('layouts.customer')
@section('title', 'Checkout')
@section('content')
<section class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_.75fr] lg:px-8">
    <div>
        <p class="text-sm font-bold uppercase tracking-[.2em] text-red-700">Final boarding call</p>
        <h1 class="mt-2 text-3xl font-black">Confirm your order</h1>
        <p class="mt-2 text-slate-600">Review your meal and choose how you will be served.</p>
        <form method="POST" action="{{ route('orders.store') }}" class="mt-7 space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" x-data="{type: '{{ old('type', 'dine_in') }}'}">
            @csrf
            <fieldset>
                <legend class="text-sm font-bold text-slate-900">Order type</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer gap-3 rounded-xl border p-4" :class="type === 'dine_in' ? 'border-red-600 bg-red-50' : 'border-slate-200'">
                        <input type="radio" name="type" value="dine_in" x-model="type" class="mt-1 border-slate-300 text-red-700 focus:ring-red-600">
                        <span><span class="block font-bold">Dine-in</span><span class="mt-1 block text-sm text-slate-500">Enjoy your meal at the restaurant.</span></span>
                    </label>
                    <label class="flex cursor-pointer gap-3 rounded-xl border p-4" :class="type === 'take_out' ? 'border-red-600 bg-red-50' : 'border-slate-200'">
                        <input type="radio" name="type" value="take_out" x-model="type" class="mt-1 border-slate-300 text-red-700 focus:ring-red-600">
                        <span><span class="block font-bold">Take-out</span><span class="mt-1 block text-sm text-slate-500">Pack your order for takeaway.</span></span>
                    </label>
                </div>
            </fieldset>
            <div x-show="type === 'dine_in'" x-cloak>
                <label for="table_number" class="block text-sm font-bold">Table number <span class="text-red-700">*</span></label>
                <input id="table_number" name="table_number" value="{{ old('table_number') }}" :required="type === 'dine_in'" maxlength="20" pattern="[A-Za-z0-9 -]+" class="mt-2 w-full rounded-xl border-slate-300 focus:border-red-600 focus:ring-red-600" placeholder="e.g. 12 or A-03">
                @error('table_number')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="special_instructions" class="block text-sm font-bold">Special instructions <span class="font-normal text-slate-500">(optional)</span></label>
                <textarea id="special_instructions" name="special_instructions" rows="4" maxlength="1000" class="mt-2 w-full rounded-xl border-slate-300 focus:border-red-600 focus:ring-red-600" placeholder="Add a request for our team.">{{ old('special_instructions') }}</textarea>
            </div>
            <div class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong>Payment note:</strong> Online payment is not enabled. After placing your order, proceed to the counter to pay and confirm your transaction.</div>
            <button type="submit" class="w-full rounded-xl bg-red-700 px-5 py-3.5 font-bold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Place order · ₱{{ number_format((float) $total, 2) }}</button>
        </form>
    </div>
    <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black">Flight summary</h2>
        <div class="mt-5 space-y-4">
            @foreach ($items as $item)
                <div class="flex justify-between gap-4 text-sm">
                    <div><p class="font-bold">{{ $item->name }}</p><p class="mt-1 text-slate-500">Qty {{ $cart[$item->id] }}</p></div>
                    <p class="shrink-0 font-bold">₱{{ number_format((float) $item->price * (int) $cart[$item->id], 2) }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-6 flex justify-between border-t border-dashed border-slate-300 pt-5"><span class="font-bold">Total</span><span class="text-xl font-black text-red-800">₱{{ number_format((float) $total, 2) }}</span></div>
        <a href="{{ route('cart.show') }}" class="mt-5 inline-flex text-sm font-bold text-red-700 hover:text-red-900">← Return to cart</a>
    </aside>
</section>
@endsection
