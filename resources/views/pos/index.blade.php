@extends('layouts.pos-register')

@section('title', 'Passenger Seat POS · Billing')
@section('pos-tab', 'billing')

@section('content')
    <h2>Unpaid bills</h2>
    <p class="mute">Payments are recorded against each existing order. Payment does not change its kitchen status.</p>

    @if ($orders->isEmpty())
        <div class="card">
            <p class="mute">No unpaid bills. Table orders appear here automatically.</p>
        </div>
    @else
        <div class="cols">
            @foreach ($orders as $order)
                <article class="card">
                    <h2>
                        {{ $order->type === 'dine_in' && $order->table_number
                            ? 'Table '.$order->table_number
                            : 'Order #'.$order->reference_number }}
                    </h2>
                    <div class="mute">
                        #{{ $order->reference_number }} · {{ str($order->status)->headline() }}
                    </div>
                    <div style="margin:6px 0">
                        @foreach ($order->items as $item)
                            <div>{{ $item->quantity }}× {{ $item->item_name }}</div>
                        @endforeach
                    </div>
                    <div class="tot">
                        <div class="g">
                            <span>Subtotal</span>
                            <span>₱{{ number_format((float) $order->items->sum(fn ($item) => (float) $item->price * (int) $item->quantity), 2) }}</span>
                        </div>
                    </div>
                    <x-pos-payment-form :order="$order" />
                    <p><a class="mute" href="{{ route('staff.pos.receipt', $order) }}">View order slip</a></p>
                </article>
            @endforeach
        </div>
    @endif

    <div>{{ $orders->links() }}</div>
@endsection

@push('scripts')
    @vite('resources/js/pos.js')
@endpush
