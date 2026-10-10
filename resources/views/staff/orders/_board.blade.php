@php $canAct = auth()->user()->hasRole('kitchen'); @endphp

<div class="grid gap-4 lg:grid-cols-3">
    @foreach ([
        ['Pending', $pending, 'bg-amber-500'],
        ['Preparing', $preparing, 'bg-sky-500'],
        ['Completed today', $completed, 'bg-emerald-500'],
    ] as [$label, $orders, $dot])
        <section aria-label="{{ $label }} orders">
            <h2 class="mb-3 flex items-center gap-2 font-display text-2xl font-bold uppercase">
                <span class="h-3 w-3 rounded-full {{ $dot }}"></span>
                {{ $label }}
                <span class="rounded-full bg-white px-2 text-sm font-semibold text-gray-600 ring-1 ring-gray-300">{{ $orders->count() }}</span>
            </h2>

            @forelse ($orders as $order)
                @include('staff.orders._card', ['order' => $order, 'canAct' => $canAct])
            @empty
                <p class="rounded-xl border-2 border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">No orders here.</p>
            @endforelse
        </section>
    @endforeach
</div>
