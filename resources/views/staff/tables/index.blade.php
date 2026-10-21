@extends('layouts.pos')

@section('title', 'Table QR control')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.2em] text-brand-700">Customer QR ordering</p>
        <h1 class="mt-1 text-3xl font-black">Table sessions</h1>
        <p class="mt-2 text-sm text-gray-600">Unpaid table sessions remain open until a cashier closes them; they do not expire automatically.</p>
    </div>
</div>

<form method="POST" action="{{ route('staff.tables.open') }}" class="mb-6 grid gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-[1fr_1fr_auto] sm:items-end">
    @csrf
    <div>
        <label for="table-number" class="block text-sm font-bold">Table number</label>
        <input id="table-number" name="table_number" type="text" maxlength="10" pattern="[A-Za-z0-9 -]{1,10}" required class="mt-2 w-full rounded-xl border-gray-300 focus:border-brand-600 focus:ring-brand-600" placeholder="e.g. 5 or A-03">
        <p class="mt-1 text-xs text-gray-500">Use the exact table number encoded in its QR link.</p>
    </div>
    <div>
        <label for="guest-count" class="block text-sm font-bold">Number of guests</label>
        <input id="guest-count" name="guest_count" type="number" min="1" max="30" value="2" required class="mt-2 w-full rounded-xl border-gray-300 focus:border-brand-600 focus:ring-brand-600">
    </div>
    <button type="submit" class="rounded-xl bg-brand-600 px-5 py-3 font-bold text-white hover:bg-brand-700">Open table QR</button>
</form>

<section aria-labelledby="table-status-heading" class="mb-6">
    <div class="mb-3 flex items-center justify-between">
        <h2 id="table-status-heading" class="text-xl font-black">Table status</h2>
        <span class="text-sm text-gray-600">Open tab · bill requested · free</span>
    </div>
    @if ($tableStatuses->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($tableStatuses as $tableStatus)
                <article class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <strong>Table {{ $tableStatus['table_number'] }}</strong>
                        <span class="text-sm font-bold">{{ $tableStatus['table_status_label'] }}</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-600">{{ $tableStatus['table_order_count'] }} orders{{ $tableStatus['qr_enabled'] ? '' : ' · QR disabled' }}</p>
                </article>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-600">No restaurant tables have been configured.</p>
    @endif
</section>

<section aria-labelledby="active-tables-heading">
    <div class="mb-3 flex items-center justify-between">
        <h2 id="active-tables-heading" class="text-xl font-black">Open tables</h2>
        <span class="rounded-full bg-gray-200 px-3 py-1 text-sm font-bold">{{ $sessions->count() }} open</span>
    </div>
    @if ($sessions->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <h3 class="font-bold">No open table sessions</h3>
            <p class="mt-1 text-sm text-gray-600">Open a table above to enable its customer QR ordering link.</p>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($sessions as $session)
                @php
                    $unfinished = $session->orders->contains(fn ($order) => in_array($order->status, ['pending', 'preparing', 'ready'], true));
                    $unpaid = $session->orders->contains(fn ($order) => $order->payment_status !== 'paid' && $order->status !== 'cancelled');
                    $canClose = ! $unfinished && ! $unpaid;
                @endphp
                <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="text-xs font-bold uppercase tracking-wider text-gray-500">Table</p><h3 class="text-2xl font-black">{{ $session->table_number }}</h3></div>
                        @if ($session->status === 'bill_requested' || $session->bill_requested_at)
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">Bill requested</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900">Open tab</span>
                        @endif
                    </div>
                    <p class="mt-3 text-sm text-gray-600">{{ $session->guest_count }} guests · Opened {{ $session->opened_at?->format('M j, g:i A') ?? 'at an unknown time' }}</p>
                    <a class="mt-3 block break-all text-sm font-semibold text-brand-700 underline" href="{{ route('customer.qr.show', ['table' => $session->table_number]) }}" target="_blank" rel="noopener">
                        {{ route('customer.qr.show', ['table' => $session->table_number]) }}
                    </a>
                    <div class="mt-4 border-t border-gray-100 pt-3">
                        @forelse ($session->orders as $order)
                            <div class="flex justify-between gap-3 py-1.5 text-sm">
                                <span class="font-semibold">{{ $order->reference_number }} · {{ ucfirst($order->status) }}</span>
                                <span>{{ $order->isPaid() ? 'Paid' : 'Unpaid' }}</span>
                            </div>
                        @empty
                            <p class="py-1.5 text-sm text-gray-500">No orders yet.</p>
                        @endforelse
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if (! $session->bill_requested_at)
                            <form method="POST" action="{{ route('staff.tables.bill', $session) }}">@csrf @method('PATCH')
                                <button class="rounded-lg border border-amber-300 px-3 py-2 text-sm font-bold text-amber-900 hover:bg-amber-50">Mark bill requested</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('staff.tables.close', $session) }}" onsubmit="return confirm('Close QR ordering for table {{ $session->table_number }}?')">@csrf @method('PATCH')
                            <button @disabled(! $canClose) class="rounded-lg bg-red-700 px-3 py-2 text-sm font-bold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50">Close QR</button>
                        </form>
                    </div>
                    @if (! $canClose)
                        <p class="mt-2 text-xs text-gray-500">Finish and record payment for all orders before closing.</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>

@endsection
