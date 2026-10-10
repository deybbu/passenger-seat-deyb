@extends('layouts.app')

@section('title', 'Your order')

@section('content')
    <h1 class="mb-4 font-display text-4xl font-bold uppercase">Your order</h1>

    @if ($lines->isEmpty())
        <div class="pass p-10 text-center">
            <p class="mb-4 text-gray-600">You have not added anything yet.</p>
            <a href="{{ route('menu.index') }}" class="inline-block rounded-full bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">Browse the menu</a>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-5">
            {{-- Order summary --}}
            <section class="pass lg:col-span-3" aria-label="Order summary">
                <ul class="divide-y divide-gray-200 px-4">
                    @foreach ($lines as $line)
                        @php $orderable = $line->item->isOrderable(); @endphp
                        <li class="py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold">{{ $line->item->name }}</p>
                                    <p class="text-sm text-gray-500">₱{{ number_format((float) $line->item->price, 2) }} each</p>
                                    @unless ($orderable)
                                        <p class="mt-1 text-sm font-semibold text-red-700">Sold out. Please remove this item.</p>
                                    @endunless
                                </div>
                                <p class="font-semibold">₱{{ number_format($line->line_total, 2) }}</p>
                            </div>

                            <div class="mt-3 flex items-center gap-2">
                                <form method="POST" action="{{ route('cart.update', $line->item) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="quantity" value="{{ $line->qty - 1 }}">
                                    <button type="submit" class="grid h-9 w-9 place-items-center rounded-full border border-gray-300 hover:bg-gray-50" aria-label="Decrease quantity of {{ $line->item->name }}">
                                        <x-icon name="minus" class="h-4 w-4" />
                                    </button>
                                </form>

                                <span class="w-8 text-center font-semibold" aria-live="polite">{{ $line->qty }}</span>

                                <form method="POST" action="{{ route('cart.update', $line->item) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="quantity" value="{{ $line->qty + 1 }}">
                                    <button type="submit" @disabled($line->qty >= \App\Services\CartService::MAX_QTY || ! $orderable)
                                            class="grid h-9 w-9 place-items-center rounded-full border border-gray-300 hover:bg-gray-50 disabled:opacity-40" aria-label="Increase quantity of {{ $line->item->name }}">
                                        <x-icon name="plus" class="h-4 w-4" />
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('cart.remove', $line->item) }}" class="ml-auto">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                                        <x-icon name="x" class="h-4 w-4" /> Remove
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="pass-divider"></div>
                <div class="flex items-center justify-between p-4">
                    <span class="font-display text-2xl font-semibold uppercase">Total</span>
                    <span class="font-display text-3xl font-bold text-brand-700">₱{{ number_format($total, 2) }}</span>
                </div>
                <p class="px-4 pb-4 text-sm"><a href="{{ route('menu.index') }}" class="font-semibold text-brand-700 underline">Add more items</a></p>
            </section>

            {{-- Checkout --}}
            @php $type = old('type', session()->has('table_number') ? 'dine_in' : ''); @endphp
            <form method="POST" action="{{ route('order.store') }}" class="pass p-4 lg:col-span-2" x-data="{ type: @js($type) }">
                @csrf
                <h2 class="font-display text-2xl font-bold uppercase">Check in</h2>

                <fieldset class="mt-3">
                    <legend class="mb-2 text-sm font-semibold">How will you enjoy your order?</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border-2 p-3 text-sm font-semibold"
                               :class="type === 'dine_in' ? 'border-brand-600 bg-brand-50' : 'border-gray-200'">
                            <input type="radio" name="type" value="dine_in" x-model="type" class="accent-brand-600" required> Dine-in
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border-2 p-3 text-sm font-semibold"
                               :class="type === 'take_out' ? 'border-brand-600 bg-brand-50' : 'border-gray-200'">
                            <input type="radio" name="type" value="take_out" x-model="type" class="accent-brand-600" required> Take-out
                        </label>
                    </div>
                </fieldset>

                <div class="mt-4" x-show="type === 'dine_in'" x-cloak>
                    <label for="table_number" class="mb-1 block text-sm font-semibold">Table number</label>
                    <input id="table_number" name="table_number" type="text" maxlength="10" :required="type === 'dine_in'"
                           value="{{ old('table_number', session('table_number')) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </div>

                <div class="mt-4">
                    <label for="customer_name" class="mb-1 block text-sm font-semibold">Your name</label>
                    <input id="customer_name" name="customer_name" type="text" maxlength="60" required autocomplete="name"
                           value="{{ old('customer_name') }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </div>

                <div class="mt-4">
                    <label for="customer_phone" class="mb-1 block text-sm font-semibold">
                        Mobile number <span class="font-normal text-gray-500" x-text="type === 'take_out' ? '(required for take-out)' : '(optional)'"></span>
                    </label>
                    <input id="customer_phone" name="customer_phone" type="tel" maxlength="20" autocomplete="tel" :required="type === 'take_out'"
                           value="{{ old('customer_phone') }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </div>

                <div class="mt-4">
                    <label for="special_instructions" class="mb-1 block text-sm font-semibold">Special instructions</label>
                    <textarea id="special_instructions" name="special_instructions" rows="3" maxlength="500"
                              placeholder="Allergies, no onions, extra spicy..."
                              class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">{{ old('special_instructions') }}</textarea>
                </div>

                @if ($hasUnavailable)
                    <p class="mt-4 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-800">Remove the sold-out items to place your order.</p>
                @endif

                <button type="submit" @disabled($hasUnavailable)
                        class="mt-5 w-full rounded-full bg-brand-600 px-6 py-3 text-base font-bold text-white hover:bg-brand-700 focus:ring-4 focus:ring-brand-100 disabled:cursor-not-allowed disabled:opacity-50">
                    Place order · ₱{{ number_format($total, 2) }}
                </button>
                <p class="mt-2 text-center text-xs text-gray-500">You will pay at the counter.</p>
            </form>
        </div>
    @endif
@endsection
