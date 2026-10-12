@php
    $orderTypeLabel = $order->type === 'dine_in' ? 'Dine-in' : 'Take-out';
    $timerStart = $order->status === 'ready' ? $order->updated_at : $order->created_at;
    $elapsedMinutes = now()->diffInMinutes($timerStart);
    $timerClass = $order->status === 'ready'
        ? 'n'
        : ($elapsedMinutes >= 15 ? 'r' : ($elapsedMinutes >= 8 ? 'y' : ''));
@endphp
<article class="oc {{ $timerClass }}" data-order-status="{{ $order->status }}">
    <div class="oh">
        <div>
            <b class="tb">{{ $order->table_number ? 'Table '.$order->table_number : $orderTypeLabel }}</b>
            <small>{{ $order->reference_number }}</small>
        </div>
        <span class="ty">{{ $orderTypeLabel }}</span>
    </div>
    <div class="tr">
        <span>Placed {{ $order->created_at->format('g:i A') }}</span>
        <span class="tm {{ $timerClass }}" data-kitchen-timer
              data-order-status="{{ $order->status }}" data-started-at="{{ $timerStart->timestamp * 1000 }}">
            <b data-timer-value></b><i data-timer-label></i>
        </span>
    </div>
    <ul>
        @foreach ($order->items as $item)
            <li><b>{{ $item->quantity }}x</b><span>{{ $item->item_name }}</span></li>
        @endforeach
        @if ($order->special_instructions)
            <li><b>Note</b><span><small>{{ $order->special_instructions }}</small></span></li>
        @endif
    </ul>
    @if ($order->status === 'pending' || $order->status === 'preparing')
        <form method="POST" action="{{ route('staff.orders.transition', $order) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $order->status === 'pending' ? 'preparing' : 'ready' }}">
            <button type="submit" class="btn {{ $order->status === 'pending' ? 'p' : 'ok' }}">
                {{ $order->status === 'pending' ? 'Accept / Start Cooking' : 'Mark as Ready' }}
            </button>
        </form>
    @else
        <div class="rd">Ready for pickup / serving</div>
    @endif
</article>
