<section id="menu">
    <div class="wrap">
        <div class="vt" role="group" aria-label="Menu layout">
            <button type="button" data-v="scroll" aria-pressed="true">Side scroll</button>
            <button type="button" data-v="show" aria-pressed="false">Showcase</button>
            <button type="button" data-v="cards" aria-pressed="false">Card grid</button>
        </div>

        <div id="vScroll">
            <div class="sh">
                <div>
                    <div class="ey">The menu</div>
                    <h2>Fly through our menu</h2>
                    <p class="note" style="margin-top:8px">Swipe, drag or use the arrows to see more, or filter by category. Menu, prices and availability are from our current menu.</p>
                </div>
                <div class="arr">
                    <button type="button" id="rp" aria-label="Previous dishes">&lsaquo;</button>
                    <button type="button" id="rn" aria-label="Next dishes">&rsaquo;</button>
                </div>
            </div>

            <div class="rcats" id="rc" role="group" aria-label="Filter menu by category">
                <button type="button" data-c="all" aria-pressed="true">All</button>
                @foreach ($menuCategories as $category)
                    <button type="button" data-c="{{ $category }}" aria-pressed="false">{{ $category }}</button>
                @endforeach
            </div>

            <div class="rail" id="rail" tabindex="0" role="region" aria-label="Menu dishes, scroll sideways">
                @forelse ($menuItems as $item)
                    @php
                        $itemOrderable = $item->isOrderable();
                        $itemAvailability = ! $item->is_available
                            ? 'Unavailable'
                            : ($item->hasSufficientIngredientStock() ? 'Available' : 'Sold out');
                        $itemPhoto = $item->image_path
                            ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->image_path)
                            : null;
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

        <div class="mg" id="vShow" hidden>
            <div class="dish {{ $boodleItems->isEmpty() ? 'dish-empty' : '' }}">
                @if ($boodleItems->isNotEmpty())
                    @php
                        $featuredBoodle = $boodleItems->first();
                        $boodlePhoto = $featuredBoodle->image_path
                            ? \Illuminate\Support\Facades\Storage::disk('public')->url($featuredBoodle->image_path)
                            : null;
                    @endphp
                    @if ($boodlePhoto)
                        <img alt="{{ $featuredBoodle->name }}" src="{{ $boodlePhoto }}" loading="lazy">
                    @else
                        <div class="dish-photo-placeholder" role="img" aria-label="No photo available">No photo available</div>
                    @endif
                    <div class="dc">
                        <h3>Boodle fight</h3>
                        <b>{{ $featuredBoodle->name }} · ₱{{ number_format((float) $featuredBoodle->price, 2) }}</b>
                        <p>{{ $featuredBoodle->description }}</p>
                    </div>
                @else
                    <div class="boodle-empty" role="status">
                        <span class="boodle-empty-icon" aria-hidden="true">✦</span>
                        <h3>Boodle fights</h3>
                        <p>Boodle fight menu items are not available right now. Check back when they are added to our menu.</p>
                    </div>
                @endif
            </div>
            <div>
                <div class="ey">The menu</div>
                <h2>Boodle fights</h2>
                @if ($boodleItems->isNotEmpty())
                    <div class="list" role="list" aria-label="Boodle fight types">
                        @foreach ($boodleItems as $item)
                            @php
                                $itemOrderable = $item->isOrderable();
                                $itemAvailability = ! $item->is_available
                                    ? 'Unavailable'
                                    : ($item->hasSufficientIngredientStock() ? 'Available' : 'Sold out');
                            @endphp
                            <article class="it" role="listitem">
                                <div class="t"><b>{{ $item->name }}</b><i></i><span>₱{{ number_format((float) $item->price, 2) }}</span></div>
                                <p>{{ $item->description }}</p>
                                <span class="av">{{ $itemAvailability }}</span>
                                @if ($itemOrderable)
                                    <button class="boodle-order" type="button" data-order-item="{{ $item->name }}">Order from your table</button>
                                @endif
                            </article>
                        @endforeach
                    </div>
                    <p class="note">Boodle fight names, descriptions, prices and availability are from the current menu.</p>
                @else
                    <p class="note" role="status">Boodle fight highlights will appear here when boodle items are added to the menu.</p>
                @endif
            </div>
        </div>

        <div id="vCards" hidden>
            <div style="text-align:center">
                <div class="ey">The menu</div>
                <h2>Boodle fights</h2>
                <p class="note" style="margin-top:8px">Boodle fight names, descriptions, prices and availability are from the current menu.</p>
            </div>
            @if ($boodleItems->isNotEmpty())
                <div class="cg2">
                    @foreach ($boodleItems as $item)
                        @php
                            $itemOrderable = $item->isOrderable();
                            $boodlePhoto = $item->image_path
                                ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->image_path)
                                : null;
                        @endphp
                        <article class="pc">
                            @if ($boodlePhoto)
                                <img alt="{{ $item->name }}" src="{{ $boodlePhoto }}" loading="lazy">
                            @else
                                <div class="pc-photo-placeholder" role="img" aria-label="No photo available">No photo</div>
                            @endif
                            <b>{{ $item->name }}</b>
                            <p>{{ $item->description }}</p>
                            <div class="pr">
                                <span>₱{{ number_format((float) $item->price, 2) }}</span>
                                @if ($itemOrderable)
                                    <button type="button" data-order-item="{{ $item->name }}" aria-label="Order {{ $item->name }}">+</button>
                                @else
                                    <button type="button" disabled aria-label="{{ $item->name }} is unavailable">×</button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="menu-empty" role="status">Boodle fight highlights will appear here when boodle items are added to the menu.</p>
            @endif
            <div class="fr">
                <div>
                    <div class="ey">Feel the taste</div>
                    <h3 class="h3 big">Share the whole spread</h3>
                    <p class="fp">A boodle fight is a Filipino communal feast. Rice, grilled favorites and sides are laid out on banana leaves for everyone at the table to share.</p>
                    <a class="btn red" href="{{ route('menu') }}">Explore the menu</a>
                </div>
                <div class="plate">
                    <div class="plate-placeholder" role="img" aria-label="Boodle fight highlights from the menu">Boodle fight highlights</div>
                </div>
            </div>
            <div class="fr rev">
                <div class="plate">
                    <div class="plate-placeholder" role="img" aria-label="Menu availability from the current menu">Menu availability</div>
                </div>
                <div>
                    <div class="ey">How it works</div>
                    <h3 class="h3 big">Gather, dig in, enjoy</h3>
                    <p class="fp">Everyone gathers around the spread and eats together. Ask our team about set sizes and options for your group.</p>
                    <a class="btn red" href="{{ route('menu') }}">Check the menu</a>
                </div>
            </div>
        </div>
    </div>
</section>
