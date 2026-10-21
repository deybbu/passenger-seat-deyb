@extends('layouts.pos-register')

@section('title', 'Passenger Seat POS · Billing')
@section('pos-tab', 'billing')

@section('content')
    <section class="pos-bill-request-region" data-pos-bill-request-region
             aria-labelledby="pos-bill-request-heading" @if ($billRequests->isEmpty()) hidden @endif>
        <h2 id="pos-bill-request-heading">Bill requests · ready for bill out</h2>
        <div class="pos-bill-request-list" data-pos-bill-request-cards>
            @foreach ($billRequests as $billRequest)
                <article class="pos-bill-request-card" data-bill-request-card="{{ $billRequest['id'] }}">
                    <svg class="pos-bill-request-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="3" width="16" height="18" rx="2"/>
                        <path d="M8 8h8M8 12h8M8 16h4"/>
                    </svg>
                    <div class="pos-bill-request-content">
                        <h3>TABLE {{ $billRequest['table_number'] }} - READY FOR BILL OUT</h3>
                        <div class="pos-bill-request-summary">
                            <span class="pos-bill-method">
                                {{ match ($billRequest['payment_method']) {
                                    'cash' => 'Cash',
                                    'gcash' => 'GCash',
                                    'card' => 'Card',
                                    default => 'Payment preference not recorded',
                                } }}
                            </span>
                            <strong>₱{{ number_format($billRequest['total'], 2) }}</strong>
                        </div>
                        <p>{{ $billRequest['order_count'] }} orders · requested {{ $billRequest['requested_for'] }}</p>
                        <a class="pos-bill-request-action" href="{{ route('staff.pos.table-session-bill', $billRequest['id']) }}">Record terminal payment</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <h2>Unpaid bills</h2>
    <p class="mute">Table QR sessions are settled together when a customer requests bill out. Payment does not change kitchen status.</p>

    @if ($tableBills->isNotEmpty())
        <h2 style="margin-top:18px">Unpaid Table QR bills</h2>
        <div class="cols">
            @foreach ($tableBills as $bill)
                <article class="card">
                    <h2>Table {{ $bill->table_number }}</h2>
                    <div class="mute">{{ $bill->orders->count() }} separate orders · settled together</div>
                    <div style="margin:8px 0">
                        @foreach ($bill->orders as $order)
                            <div style="padding:8px 0;border-top:1px solid var(--bd)">
                                <strong>#{{ $order->reference_number }}</strong>
                                <span class="mute">· {{ str($order->status)->headline() }}</span>
                                <div class="mute">Order total: ₱{{ number_format((float) $order->total_amount, 2) }}</div>
                                @foreach ($order->items as $item)
                                    <div>
                                        {{ $item->quantity }}× {{ $item->item_name }}
                                        @if ($item->variant_name_snapshot)
                                            · {{ $item->variant_name_snapshot }}
                                        @endif
                                        @if ($item->modifier_snapshot)
                                            · {{ collect($item->modifier_snapshot)->pluck('name')->filter()->join(', ') }}
                                        @endif
                                    </div>
                                @endforeach
                                @if ($order->special_instructions)
                                    <div class="mute">Note: {{ $order->special_instructions }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <x-pos-payment-form
                        :action="route('staff.pos.table-paid', ['restaurantTable' => $bill->table_id])"
                        :form-id="'table-payment-'.$bill->table_id"
                        :subtotal="$bill->subtotal"
                        :vat-mode="'added'"
                    />
                </article>
            @endforeach
        </div>
    @endif

    @if ($orders->isNotEmpty())
        <h2 style="margin-top:18px">Other unpaid orders</h2>
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
                            <div>
                                {{ $item->quantity }}× {{ $item->item_name }}
                                @if ($item->variant_name_snapshot)
                                    · {{ $item->variant_name_snapshot }}
                                @endif
                                @if ($item->modifier_snapshot)
                                    · {{ collect($item->modifier_snapshot)->pluck('name')->filter()->join(', ') }}
                                @endif
                            </div>
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
    @if ($tableBills->isEmpty() && $orders->isEmpty())
        <div class="card"><p class="mute">No unpaid bills. Requested table-session bills appear here when ready for bill out.</p></div>
    @endif
@endsection

@push('scripts')
    @vite('resources/js/pos.js')
@endpush
