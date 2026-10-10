@extends('layouts.customer')
@section('title', 'Your Cart')
@section('content')
<section class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
    <p class="text-sm font-bold uppercase tracking-[.2em] text-red-700">Passenger manifest</p>
    <h1 class="mt-2 text-3xl font-black">Your cart</h1>
    @php
        $items = \App\Models\MenuItem::query()->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $total = $items->sum(fn ($item) => (float) $item->price * (int) ($cart[$item->id] ?? 0));
    @endphp
    @if (count($cart) === 0 || $items->isEmpty())
        <div class="mt-7 rounded-2xl border border-slate-200 bg-white p-10 text-center">
            <h2 class="text-xl font-bold">Your cart is waiting for passengers.</h2>
            <p class="mt-2 text-slate-600">Add a meal from the boarding menu to get started.</p>
            <a href="{{ route('menu.index') }}" class="mt-5 inline-flex rounded-xl bg-red-700 px-5 py-3 font-bold text-white">Browse menu</a>
        </div>
    @else
        <div class="mt-7 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            @foreach ($items as $item)
                <div class="flex flex-col gap-4 border-b border-slate-100 p-5 last:border-0 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0"><h2 class="font-extrabold">{{ $item->name }}</h2><p class="mt-1 text-sm text-slate-500">₱{{ number_format((float) $item->price, 2) }} each</p></div>
                    <div class="flex flex-wrap items-center gap-3">
                        <form method="POST" action="{{ route('cart.update', $item) }}" class="flex items-center gap-2">
                            @csrf @method('PATCH')
                            <label for="qty-{{ $item->id }}" class="text-sm font-semibold">Qty</label>
                            <input id="qty-{{ $item->id }}" name="quantity" type="number" min="0" max="99" value="{{ $cart[$item->id] ?? 1 }}" class="w-20 rounded-lg border-slate-300 text-sm focus:border-red-600 focus:ring-red-600">
                            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold hover:bg-slate-50">Update</button>
                        </form>
                        <form method="POST" action="{{ route('cart.remove', $item) }}">@csrf @method('DELETE')<button class="rounded-lg px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-50">Remove</button></form>
                    </div>
                    <p class="font-black sm:w-28 sm:text-right">₱{{ number_format((float) $item->price * (int) $cart[$item->id], 2) }}</p>
                </div>
            @endforeach
            <div class="flex flex-col gap-4 bg-slate-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-sm text-slate-500">Estimated total</p><p class="text-2xl font-black text-red-800">₱{{ number_format((float) $total, 2) }}</p></div>
                <a href="{{ route('checkout') }}" class="rounded-xl bg-red-700 px-6 py-3 text-center font-bold text-white hover:bg-red-800">Continue to checkout</a>
            </div>
        </div>
    @endif
</section>
@endsection
