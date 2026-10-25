<section id="menu">
    <div class="wrap">
        <div class="vt" role="group" aria-label="Menu layout">
            <button type="button" data-v="scroll" aria-pressed="true">Side Scroll</button>
            <a class="vt-link" href="#story">Our Story</a>
        </div>

        <div id="vScroll">
            <div class="sh">
                <div>
                    <div class="ey">The Menu</div>
                    <h2>Fly Through Our Menu</h2>
                    <p class="note" style="margin-top:8px">Swipe, drag or use the arrows to see more, or filter by category. Menu, prices and availability are from our current menu.</p>
                </div>
                <div class="arr">
                    <button type="button" id="rp" aria-label="Previous dishes">&lsaquo;</button>
                    <button type="button" id="rn" aria-label="Next dishes">&rsaquo;</button>
                </div>
            </div>

            <div class="rcats" id="rc" role="group" aria-label="Filter menu by category" tabindex="0">
                <button type="button" data-c="all" aria-pressed="true">All</button>
                @foreach ($menuCategories as $category)
                    <button type="button" data-c="{{ $category }}" aria-pressed="false">{{ $category }}</button>
                @endforeach
            </div>
            <div class="rcats-progress" aria-hidden="true">
                <span id="rcProgress"></span>
            </div>

            <div class="rail" id="rail" tabindex="0" role="region" aria-label="Menu dishes, scroll sideways">
                @forelse ($menuItems as $item)
                    @php
                        $itemOrderable = $item->isOrderable();
                        $itemAvailability = ! $item->is_available
                            ? 'Unavailable'
                            : ($item->hasSufficientIngredientStock() ? 'Available' : 'Sold out');
                        $itemPhoto = \App\Support\MenuImageUrl::forPath($item->image_path);
                        $itemCategory = $item->category?->name ?? 'Uncategorized';
                    @endphp
                    <article class="mi" data-menu-card data-category="{{ $itemCategory }}">
                        @if ($itemPhoto)
                            <img alt="{{ $item->name }}" loading="lazy" decoding="async" draggable="false" src="{{ $itemPhoto }}">
                        @else
                            <div class="mi-placeholder" role="img" aria-label="No photo available">
                                <span>No photo available</span>
                            </div>
                        @endif
                        <span class="cat">{{ $itemCategory }}</span>
                        <div class="mc">
                            <h3>{{ $item->name }}</h3>
                            <p>{{ $item->description }}</p>
                            <div class="pr">
                                <span>₱{{ number_format((float) $item->price, 2) }} · {{ $itemAvailability }}</span>
                                @if ($itemOrderable)
                                    <button type="button" data-order-item="{{ $item->name }}" aria-label="Order {{ $item->name }}">+</button>
                                @else
                                    <button type="button" disabled aria-label="{{ $item->name }} is {{ strtolower($itemAvailability) }}">×</button>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="menu-empty" role="status">Our menu is being updated. Please check back soon.</p>
                @endforelse
            </div>
            <div class="prog" aria-hidden="true"><i id="pb"></i></div>
        </div>

    </div>
</section>
