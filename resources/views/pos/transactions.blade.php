@extends('layouts.staff')

@section('title', 'Ledger')

@section('content')
    <h1 class="mb-4 font-display text-4xl font-bold uppercase leading-none">Transaction ledger</h1>

    <form method="GET" action="{{ route('pos.transactions') }}" class="pass mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2">
            <label for="q" class="mb-1 block text-xs font-semibold text-gray-600">Search</label>
            <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Reference, name or table" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="from" class="mb-1 block text-xs font-semibold text-gray-600">From</label>
            <input id="from" name="from" type="date" value="{{ request('from') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="to" class="mb-1 block text-xs font-semibold text-gray-600">To</label>
            <input id="to" name="to" type="date" value="{{ request('to') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="status" class="mb-1 block text-xs font-semibold text-gray-600">Order status</label>
            <select id="status" name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach (['pending', 'preparing', 'ready', 'served', 'cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="payment_status" class="mb-1 block text-xs font-semibold text-gray-600">Payment</label>
            <select id="payment_status" name="payment_status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="paid" @selected(request('payment_status') === 'paid')>Paid</option>
                <option value="unpaid" @selected(request('payment_status') === 'unpaid')>Unpaid</option>
            </select>
        </div>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
            <button type="submit" class="rounded-full bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">Apply filters</button>
            <a href="{{ route('pos.transactions') }}" class="rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold hover:bg-gray-50">Clear</a>
        </div>
    </form>

    <p class="mb-3 text-sm text-gray-600">
        {{ $orders->total() }} order(s). Paid total for this filter:
        <span class="font-bold text-brand-700">₱{{ number_format($paidTotal, 2) }}</span>
    </p>

    <div class="pass overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Service</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Payment</th>
                    <th class="px-4 py-3"><span class="sr-only">Receipt</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($orders as $order)
                    <tr>
                        <td class="px-4 py-3 font-semibold">{{ $order->reference_number }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $order->created_at->format('M d, Y g:i A') }}</td>
                        <td class="px-4 py-3">{{ $order->customer_name }}</td>
                        <td class="px-4 py-3">{{ $order->type_label }}@if ($order->table_number), T{{ $order->table_number }}@endif</td>
                        <td class="px-4 py-3 text-right font-semibold">₱{{ number_format((float) $order->total_amount, 2) }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$order->status" /></td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$order->payment_status" />
                            @if ($order->payment_method)<span class="ml-1 text-xs uppercase text-gray-500">{{ $order->payment_method }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('pos.receipt', $order) }}" class="font-semibold text-brand-700 underline">Receipt</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No orders match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
