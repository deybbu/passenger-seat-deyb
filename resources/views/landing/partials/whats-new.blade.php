<section class="soft" id="whatsnew">
    <div class="wrap">
        <div class="ey">What's new</div>
        <h2>Fresh from the kitchen</h2>
        <p style="color:var(--mute);max-width:520px">Featured dishes from our current menu.</p>
        <div class="wn" aria-live="polite">
            @forelse ($featuredItems as $item)
                @php
                    $itemPhoto = $item->image_path
                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->image_path)
                        : null;
                @endphp
                <article class="nc">
                    <div class="meta">
                        <span class="tag t-dish">Featured dish</span>
                        <span>₱{{ number_format((float) $item->price, 2) }}</span>
                    </div>
                    @if ($itemPhoto)
                        <img class="nc-photo" src="{{ $itemPhoto }}" alt="{{ $item->name }}" loading="lazy" decoding="async">
                    @endif
                    <h3>{{ $item->name }}</h3>
                    <p>{{ $item->description }}</p>
                    <a class="go" href="{{ route('menu') }}">See the menu</a>
                </article>
            @empty
                <p class="menu-empty" role="status">No featured dishes are available right now. Check back soon.</p>
            @endforelse
        </div>
    </div>
</section>
