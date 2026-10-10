@extends('layouts.staff')

@section('title', 'Menu availability')

@section('content')
    <h1 class="mb-1 font-display text-4xl font-bold uppercase leading-none">Menu availability</h1>
    <p class="mb-4 text-sm text-gray-600">Switch a dish off to show it as sold out to customers. Dishes also show as sold out automatically when their ingredients run short.</p>

    @foreach ($categories as $category)
        <section class="mb-6">
            <h2 class="mb-2 border-l-8 border-brand-600 pl-3 font-display text-2xl font-bold uppercase">{{ $category->name }}</h2>
            <div class="pass divide-y divide-gray-200">
                @foreach ($category->items as $item)
                    @php $short = $item->is_available && ! $item->isOrderable(); @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="font-semibold">{{ $item->name }}</p>
                            <p class="text-sm text-gray-500">
                                ₱{{ number_format((float) $item->price, 2) }}
                                @if ($short) · <span class="font-semibold text-red-700">Out of stock (ingredients)</span> @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('admin.menu.toggle', $item) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="rounded-full px-4 py-2 text-sm font-semibold {{ $item->is_available ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                                {{ $item->is_available ? 'Available (tap to mark sold out)' : 'Sold out (tap to restore)' }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
