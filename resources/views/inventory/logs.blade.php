@extends('layouts.staff')

@section('title', 'Stock log')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <h1 class="font-display text-4xl font-bold uppercase leading-none">Stock log</h1>
        <form method="GET" action="{{ route('inventory.logs') }}" class="flex gap-2">
            <select name="ingredient" aria-label="Ingredient" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">All ingredients</option>
                @foreach ($ingredients as $ingredient)
                    <option value="{{ $ingredient->id }}" @selected((string) request('ingredient') === (string) $ingredient->id)>{{ $ingredient->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-full bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>
    </div>

    <div class="pass overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Ingredient</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3 text-right">Quantity</th>
                    <th class="px-4 py-3">By</th>
                    <th class="px-4 py-3">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $log->ingredient->name }}</td>
                        <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $log->type) }}</td>
                        <td class="px-4 py-3 text-right font-semibold">
                            {{ ($log->type === 'stock_out' ? '−' : ((float) $log->quantity > 0 && $log->type === 'adjustment' ? '+' : '')) }}{{ rtrim(rtrim(number_format((float) $log->quantity, 3, '.', ''), '0'), '.') }} {{ $log->ingredient->unit }}
                        </td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? 'Customer order' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $log->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Nothing logged yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
