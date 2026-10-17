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
            <form method="POST" action="{{ route('order.store') }}" class="pass p-4 lg:col-span-2">
                @csrf
                <div data-react-root="cart-checkout">
                    <script type="application/json" data-props>
                        @json([
                            'type' => $type,
                            'tableNumber' => old('table_number', session('table_number')),
                            'customerName' => old('customer_name'),
                            'customerPhone' => old('customer_phone'),
                            'specialInstructions' => old('special_instructions'),
                            'hasUnavailable' => $hasUnavailable,
                            'total' => (float) $total,
                        ])
                    </script>
                </div>
            </form>
        </div>
    @endif
@endsection
