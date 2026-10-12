@php $orderable = $item->isOrderable(); @endphp
<div class="flex items-center justify-between gap-3 px-4 py-3 {{ $orderable ? '' : 'opacity-50' }}">
    <div>
        <p class="font-semibold">{{ $item->name }}</p>
        <p class="text-sm text-gray-500">₱{{ number_format((float) $item->price, 2) }}@unless ($orderable) · Sold out @endunless</p>
    </div>

    @if ($orderable)
        <div class="flex items-center gap-2">
            <button type="button" @click="bump({{ $item->id }}, -1)" class="grid h-9 w-9 place-items-center rounded-full border border-gray-300 hover:bg-gray-50" aria-label="Decrease {{ $item->name }}"><x-icon name="minus" class="h-4 w-4" /></button>
            <input type="number" name="items[{{ $item->id }}]" min="0" max="20" x-model.number="qty[{{ $item->id }}]"
                   class="w-14 rounded-lg border border-gray-300 px-2 py-1.5 text-center" aria-label="Quantity of {{ $item->name }}">
            <button type="button" @click="bump({{ $item->id }}, 1)" class="grid h-9 w-9 place-items-center rounded-full border border-gray-300 hover:bg-gray-50" aria-label="Increase {{ $item->name }}"><x-icon name="plus" class="h-4 w-4" /></button>
        </div>
    @endif
</div>
