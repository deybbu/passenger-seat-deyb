<article class="pass mb-3 p-4">
    <div class="flex items-start justify-between gap-2">
        <div>
            <p class="font-display text-2xl font-bold leading-none text-brand-700">{{ $order->reference_number }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $order->created_at->format('g:i A') }} ({{ $order->created_at->diffForHumans() }})</p>
        </div>
        <div class="flex flex-col items-end gap-1">
            <x-status-badge :status="$order->status" />
            <x-status-badge :status="$order->payment_status" />
        </div>
    </div>

    <p class="mt-2 text-sm font-semibold">
        {{ $order->type_label }}@if ($order->table_number), Table {{ $order->table_number }}@endif
        <span class="font-normal text-gray-600">for {{ $order->customer_name }}</span>
    </p>

    <ul class="mt-2 space-y-0.5 text-sm">
        @foreach ($order->items as $item)
            <li>
                <span class="font-semibold">{{ $item->quantity }}×</span> {{ $item->name }}
                @if ($item->variant_name_snapshot)
                    <span class="text-gray-600">· {{ $item->variant_name_snapshot }}</span>
                @endif
                @if ($item->modifier_snapshot)
                    <span class="text-gray-600">· {{ collect($item->modifier_snapshot)->pluck('name')->filter()->join(', ') }}</span>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($order->special_instructions)
        <p class="mt-3 border-l-4 border-brand-600 bg-brand-50 p-2 text-sm"><span class="font-semibold">Request:</span> {{ $order->special_instructions }}</p>
    @endif

    @if ($canAct && in_array($order->status, ['pending', 'preparing'], true))
        <div class="mt-3 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('staff.orders.transition', $order) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $order->status === 'pending' ? 'preparing' : 'ready' }}">
                <button type="submit" class="inline-flex items-center gap-1 rounded-full bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                    <x-icon name="check" class="h-4 w-4" />
                    {{ $order->status === 'pending' ? 'Start preparing' : 'Mark ready' }}
                </button>
            </form>

            <form method="POST" action="{{ route('staff.orders.transition', $order) }}"
                  onsubmit="return confirm('Cancel {{ $order->reference_number }}? Any consumed ingredients will be restored to stock.')">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="cancelled">
                <button type="submit" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
            </form>
        </div>
    @endif

    @if ($order->status === 'ready')
        <div class="mt-3 flex flex-wrap gap-2">
            @if (auth()->user()->hasRole('cashier'))
                <form method="POST" action="{{ route('staff.orders.complete', $order) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Mark completed</button>
                </form>
                <form method="POST" action="{{ route('staff.orders.transition', $order) }}" onsubmit="return confirm('Cancel {{ $order->reference_number }}? Consumed ingredients will be restored to stock.')">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                </form>
            @endif
        </div>
    @endif
</article>
