@extends('layouts.customer')
@section('title', 'Checkout')
@section('content')
<section class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_.75fr] lg:px-8">
    <div>
        <p class="text-sm font-bold uppercase tracking-[.2em] text-red-700">Final boarding call</p>
        <h1 class="mt-2 text-3xl font-black">Confirm your order</h1>
        <p class="mt-2 text-slate-600">Review your meal and choose how you will be served.</p>
        <form method="POST" action="{{ route('orders.store') }}" class="mt-7 space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <div data-react-root="checkout">
                <script type="application/json" data-props>
                    @json([
                        'type' => old('type', 'dine_in'),
                        'tableNumber' => old('table_number'),
                        'tableError' => $errors->first('table_number'),
                        'specialInstructions' => old('special_instructions'),
                        'total' => (float) $total,
                    ])
                </script>
            </div>
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
