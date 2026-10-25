@extends('layouts.pos-register')

@section('title', 'Passenger Seat POS · Orders')
@section('pos-tab', 'orders')

@section('content')
    @php
        $statuses = [
            'pending' => ['Pending', 'b-p'],
            'preparing' => ['Preparing', 'b-c'],
            'ready' => ['Ready', 'b-r'],
            'served' => ['Served', 'b-d'],
            'cancelled' => ['Cancelled', ''],
        ];
    @endphp

    <section class="cols" aria-label="Orders by status" data-pos-orders>
        @foreach ($statuses as $status => [$label, $badgeClass])
            @php($statusOrders = $orders->get($status, collect()))
            <div class="col">
                <h2><span>{{ $label }}</span><span class="badge {{ $badgeClass }}">{{ $statusOrders->count() }}</span></h2>
                @forelse ($statusOrders as $order)
                    <article class="ord">
                        <b>#{{ $order->reference_number }}</b>
                        <span class="badge {{ $badgeClass }}">{{ $label }}</span>
                        <span class="badge">{{ $order->isPaid() ? 'Paid' : 'Unpaid' }}</span>
                        <div class="mute">
                            {{ $order->type === 'dine_in' ? 'Dine-in' : 'Take-out' }}
                            @if ($order->table_number) · Table {{ $order->table_number }} @endif
                            · ₱{{ number_format((float) $order->total_amount, 2) }}
                        </div>
                        <div>
                            @foreach ($order->items as $item)
                                {{ $item->quantity }}× {{ $item->item_name }}@if (! $loop->last), @endif
                            @endforeach
                        </div>
                        <div class="seg">
                            @if ($status === 'ready')
                                <form method="POST" action="{{ route('staff.orders.complete', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                <button class="btn sm red" type="submit">Mark Served</button>
                                </form>
                            @endif
                            @if (in_array($status, ['pending', 'preparing'], true) && ! $order->isPaid())
                                <form method="POST" action="{{ route('staff.orders.transition', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="cancelled">
                                    <button class="btn sm" type="submit">Cancel</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="mute">None</p>
                @endforelse
            </div>
        @endforeach
    </section>
@endsection
