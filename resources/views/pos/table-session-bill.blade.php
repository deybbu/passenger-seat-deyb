@extends('layouts.pos-register')

@section('title', 'Table '.$tableSession->table_number.' · Bill out')
@section('pos-tab', 'billing')

@section('content')
    @php
        $unfinishedOrders = $tableSession->orders->filter(
            fn ($order) => ! in_array($order->status, ['completed', 'cancelled'], true),
        );
    @endphp

    <div class="pos-page-heading">
        <div>
            <p class="pos-eyebrow">Cashier · Table bill out</p>
            <h1>Table {{ $tableSession->table_number }}</h1>
            <p>{{ $tableSession->orders->where('status', '!=', 'cancelled')->count() }} batches · requested {{ $tableSession->bill_requested_at?->diffForHumans() ?? 'recently' }}</p>
        </div>
        <a class="pos-button" href="{{ route('staff.pos.index') }}">Back to billing</a>
    </div>

    @if ($unfinishedOrders->isNotEmpty())
        <div class="card" role="status" style="border-left:4px solid #d97706;margin-bottom:16px">
            <strong>Kitchen warning:</strong>
            {{ $unfinishedOrders->count() }} batch{{ $unfinishedOrders->count() === 1 ? '' : 'es' }}
            {{ $unfinishedOrders->count() === 1 ? 'is' : 'are' }} not completed yet. You may still bill out this table.
        </div>
    @endif

    <div class="cols">
        <section class="card">
            <h2>Orders on this table</h2>
            @foreach ($tableSession->orders as $order)
                <article style="padding:12px 0;border-top:1px solid var(--bd)">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                        <strong>Table {{ $tableSession->table_number }} - Order {{ $order->batch_number }}</strong>
                        <span class="pos-badge {{ $order->status === 'cancelled' ? 'is-unpaid' : '' }}">{{ str($order->status)->headline() }}</span>
                    </div>
                    <p class="mute">#{{ $order->reference_number }} · {{ $order->created_at?->format('Y-m-d H:i') }}</p>
                    @forelse ($order->items as $item)
                        <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;{{ $item->cancelled_at ? 'text-decoration:line-through;opacity:.65' : '' }}">
                            <span>
                                {{ $item->quantity }} × {{ $item->item_name }}
                                @if ($item->variant_name_snapshot)
                                    · {{ $item->variant_name_snapshot }}
                                @endif
                                @if ($item->modifier_snapshot)
                                    · {{ collect($item->modifier_snapshot)->pluck('name')->filter()->join(', ') }}
                                @endif
                                @if ($item->cancelled_at)
                                    <small>(cancelled)</small>
                                @endif
                            </span>
                            <span>₱{{ number_format((float) $item->price * (int) $item->quantity, 2) }}</span>
                        </div>
                    @empty
                        <p class="mute">No items in this batch.</p>
                    @endforelse
                    @if ($order->special_instructions)
                        <p class="mute">Instructions: {{ $order->special_instructions }}</p>
                    @endif
                </article>
            @endforeach
        </section>

        <section class="card">
            <h2>Bill summary</h2>
            <div class="tot">
                <div class="g"><span>Subtotal</span><strong>₱{{ number_format($snapshot['subtotal'], 2) }}</strong></div>
                @if ($snapshot['vat_exemption'] > 0)
                    <div class="g"><span>VAT exemption</span><strong>−₱{{ number_format($snapshot['vat_exemption'], 2) }}</strong></div>
                @endif
                <div class="g"><span>Discount</span><strong>−₱{{ number_format($snapshot['discount'], 2) }}</strong></div>
                <div class="g"><span>VAT (12%, included)</span><strong>₱{{ number_format($snapshot['vat'], 2) }}</strong></div>
                <div class="g"><strong>Total</strong><strong>₱{{ number_format($snapshot['total'], 2) }}</strong></div>
            </div>
            <p class="mute">Senior Citizen / PWD discount may be applied with the cardholder's name and ID. The cashier's recorded payment method is used for reports.</p>
            <p class="mute">If the customer already paid on a separate terminal, confirm the method used here to record that payment in Passenger Seat. This records the sale and closes the table bill; it does not charge the customer again.</p>

            <x-pos-payment-form
                :action="route('staff.pos.table-session-paid', $tableSession)"
                :form-id="'session-payment-'.$tableSession->id"
                :subtotal="$snapshot['subtotal']"
                :vat-mode="'included'"
                :requested-payment-method="$snapshot['requested_payment_method']"
            />
        </section>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/pos.js')
@endpush
