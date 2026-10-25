@php
    $lowStockItems = $ingredients->filter(fn ($ingredient) => $ingredient->isLowStock());
    $recipeItems = $menuItems->filter(fn ($item) => $item->ingredients->isNotEmpty());
    $stockWarningText = 'Warning: '.$lowStockItems->map(fn ($ingredient) => $ingredient->name.' '.($ingredient->usableStock() <= 0 ? 'out of stock' : 'running low').' ('.$ingredient->usableStock().' usable '.$ingredient->unit.', min '.$ingredient->min_threshold.' '.$ingredient->unit.')')->join(' · ');
@endphp
<span hidden data-low-stock-total="{{ $lowStockCount }}" data-stock-warning-text-value="{{ $stockWarningText }}"></span>
<div class="g2">
    <div class="stack">
        <section class="card">
            <h2>Ingredient stock</h2>
            @forelse ($ingredients as $ingredient)
                @php
                    $usableStock = $ingredient->usableStock();
                    $outOfStock = $usableStock <= 0;
                    $low = $ingredient->isLowStock();
                    $stockClass = $outOfStock ? 'er' : ($low ? 'wn' : 'ok');
                    $stockLabel = $outOfStock ? 'Out of stock' : ($low ? 'Low stock' : 'In stock');
                @endphp
                <div class="row">
                    <div>
                        <b>{{ $ingredient->name }}</b>
                        <small title="{{ number_format((float) ($ingredient->reserved_quantity ?? 0), 3) }} {{ $ingredient->unit }} reserved">{{ number_format($usableStock, 3) }} usable {{ $ingredient->unit }} · minimum {{ $ingredient->min_threshold }} {{ $ingredient->unit }}</small>
                    </div>
                    <span class="b {{ $stockClass }}">{{ $stockLabel }}</span>
                </div>
            @empty
                <div class="empty">No ingredients have been added yet.</div>
            @endforelse
        </section>
        <section class="card">
            <h2>Automatic stock deduction</h2>
            <p>When an order starts preparing, each menu item's saved recipe is deducted from stock automatically.</p>
            @forelse ($recipeItems as $item)
                <div class="row">
                    <div>
                        <b>{{ $item->name }}</b>
                        <small>Raw needed per serving (with yield loss): {{ $item->ingredients->map(fn ($ingredient) => $ingredient->rawQuantityForPortion((float) $ingredient->pivot->quantity).' '.$ingredient->unit.' '.$ingredient->name)->join(' · ') }}</small>
                    </div>
                </div>
            @empty
                <div class="empty">No recipes have been added yet.</div>
            @endforelse
        </section>
    </div>
    <div class="stack">
        <section class="card">
            <h2>Menu availability (automatic)</h2>
            <p>An item shows Not available on the public menu as soon as its saved recipe cannot be fulfilled from stock.</p>
            @forelse ($menuItems as $item)
                @php
                    $orderable = $item->isOrderable();
                    $shortIngredients = $item->ingredients->filter(
                        fn ($ingredient) => $ingredient->usableStock()
                            - (float) ($ingredient->reserved_quantity ?? 0)
                            + 0.0005 < $ingredient->rawQuantityForPortion((float) $ingredient->pivot->quantity)
                    );
                @endphp
                <div class="row">
                    <div>
                        <b>{{ $item->name }}</b>
                        <small>{{ $shortIngredients->isNotEmpty()
                            ? 'Out of stock: '.$shortIngredients->pluck('name')->join(', ')
                            : ($orderable ? 'All ingredients in stock' : 'Unavailable in menu') }}</small>
                    </div>
                    <span class="b {{ $orderable ? 'ok' : 'er' }}" @if (! $orderable && $shortIngredients->isNotEmpty()) title="Sold out (recipe stock)" @endif>{{ $orderable ? 'Available' : 'Not available' }}</span>
                </div>
            @empty
                <div class="empty">No menu items have been added yet.</div>
            @endforelse
        </section>
        <section class="card">
            <h2>Inventory log (automatic)</h2>
            @forelse ($inventoryLogs as $log)
                @php
                    $movement = match ($log->type) {
                        'stock_out' => 'Auto-deduct',
                        'stock_in' => 'Stock received',
                        'prep_out' => 'Prep out',
                        'prep_in' => 'Prep in',
                        'waste' => 'Waste',
                        default => 'Adjustment',
                    };
                    $quantitySign = in_array($log->type, ['stock_out', 'prep_out', 'waste'], true)
                        || ($log->type === 'adjustment' && (float) $log->quantity < 0)
                        ? '-'
                        : '+';
                @endphp
                <div class="row">
                    <div>
                        <b>{{ $movement }} {{ $quantitySign }}{{ number_format(abs((float) $log->quantity), 3) }} {{ $log->ingredient?->unit }} · {{ $log->ingredient?->name ?? 'Ingredient removed' }}</b>
                        <small>{{ $log->created_at->format('g:i A') }} · {{ $log->user?->name ?? 'System' }} · {{ $log->reason ? $log->reason.' · ' : '' }}{{ $log->notes ?: ($log->order?->reference_number ?? 'Inventory activity') }}</small>
                    </div>
                </div>
            @empty
                <div class="empty">No automatic entries yet. Inventory activity will appear here after stock changes.</div>
            @endforelse
        </section>
    </div>
</div>
