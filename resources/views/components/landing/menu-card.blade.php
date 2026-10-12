<article class="landing-menu-card">
    @if ($item->image_path)
        <img
            class="landing-menu-photo"
            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->image_path) }}"
            alt="{{ $item->name }}"
            loading="lazy"
            decoding="async"
        >
    @else
        <div class="landing-menu-photo" role="img" aria-label="No photo available">
            <span>No photo available</span>
        </div>
    @endif

    @php
        $hasIngredientStock = $item->hasSufficientIngredientStock();
        $availability = ! $item->is_available
            ? 'Unavailable'
            : ($hasIngredientStock ? 'Available' : 'Sold out');
    @endphp

    <div class="landing-menu-card-body">
        <div class="landing-menu-card-heading">
            <h2>{{ $item->name }}</h2>
            <span class="landing-menu-price">₱{{ number_format((float) $item->price, 2) }}</span>
        </div>

        @if ($item->description)
            <p class="landing-menu-description">{{ $item->description }}</p>
        @endif

        <div class="landing-menu-card-meta">
            <span class="landing-menu-category">{{ $item->category?->name ?? 'Uncategorized' }}</span>
            <span class="landing-menu-availability {{ $item->is_available && $hasIngredientStock ? '' : 'is-unavailable' }}">
                {{ $availability }}
            </span>
        </div>
    </div>
</article>
