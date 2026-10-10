@extends('layouts.staff')

@section('title', $ingredient->exists ? 'Edit ingredient' : 'New ingredient')

@section('content')
    <div class="mx-auto max-w-lg">
        <h1 class="mb-4 font-display text-4xl font-bold uppercase leading-none">{{ $ingredient->exists ? 'Edit ingredient' : 'New ingredient' }}</h1>

        <form method="POST" action="{{ $ingredient->exists ? route('inventory.update', $ingredient) : route('inventory.store') }}" class="pass space-y-4 p-5">
            @csrf
            @if ($ingredient->exists) @method('PUT') @endif

            <div>
                <label for="name" class="mb-1 block text-sm font-semibold">Name</label>
                <input id="name" name="name" type="text" maxlength="100" required value="{{ old('name', $ingredient->name) }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="unit" class="mb-1 block text-sm font-semibold">Unit</label>
                    <input id="unit" name="unit" type="text" maxlength="20" required placeholder="kg, L, pcs" value="{{ old('unit', $ingredient->unit) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </div>
                <div>
                    <label for="min_threshold" class="mb-1 block text-sm font-semibold">Low-stock level</label>
                    <input id="min_threshold" name="min_threshold" type="number" step="0.001" min="0" required value="{{ old('min_threshold', $ingredient->min_threshold) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </div>
            </div>

            @unless ($ingredient->exists)
                <div>
                    <label for="current_stock" class="mb-1 block text-sm font-semibold">Opening stock</label>
                    <input id="current_stock" name="current_stock" type="number" step="0.001" min="0" value="{{ old('current_stock', 0) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </div>
            @else
                <p class="text-sm text-gray-500">Change the stock amount with "Log stock" on the ledger so the change is recorded.</p>
            @endunless

            <div class="flex gap-2">
                <button type="submit" class="rounded-full bg-brand-600 px-6 py-2.5 font-bold text-white hover:bg-brand-700">Save</button>
                <a href="{{ route('inventory.index') }}" class="rounded-full border border-gray-300 px-6 py-2.5 font-semibold hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
@endsection
