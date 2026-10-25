<div class="cols" data-order-columns>
    @foreach ([
        ['pending', 'Pending', $pending],
        ['preparing', 'Preparing', $preparing],
        ['ready', 'Ready', $ready],
    ] as [$status, $label, $orders])
        <section class="col" data-order-column="{{ $status }}" aria-label="{{ $label }} orders">
            <h2>{{ $label }} <span class="cnt" data-column-count>{{ $orders->count() }}</span></h2>
            @forelse ($orders as $order)
                @include('staff.kitchen._order-card', ['order' => $order])
            @empty
                <div class="empty">No {{ strtolower($label) }} orders</div>
            @endforelse
        </section>
    @endforeach
</div>
