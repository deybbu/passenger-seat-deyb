@props(['status'])
@php
    $classes = match ($status) {
        'pending' => 'bg-amber-100 text-amber-800',
        'preparing' => 'bg-sky-100 text-sky-800',
        'served' => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-gray-200 text-gray-600',
        'paid' => 'bg-emerald-100 text-emerald-800',
        'unpaid' => 'bg-red-100 text-red-700',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize $classes"]) }}>{{ $status }}</span>
