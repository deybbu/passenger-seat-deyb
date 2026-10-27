@if ($newItems->isNotEmpty())
    <section class="soft" id="whatsnew">
        <div class="wrap">
            <div class="ey">What's New</div>
            <h2>Fresh From the Kitchen</h2>
            <p style="color:var(--mute);max-width:520px">New dishes from our current menu.</p>
            <div class="wn">
            @foreach ($newItems as $item)
                @php
                    $itemPhoto = \App\Support\MenuImageUrl::forPath($item->image_path);
                @endphp
                <article class="nc">
                    <div class="meta">
                        <span class="tag t-dish">New</span>
                        <span>₱{{ number_format((float) $item->price, 2) }}</span>
                    </div>
                    @if ($itemPhoto)
                        <img class="nc-photo" src="{{ $itemPhoto }}" alt="{{ $item->name }}" loading="lazy" decoding="async">
                    @endif
                    <h3>{{ $item->name }}</h3>
                    @if ($item->description)
                        <p><strong>Description:</strong> {{ $item->description }}</p>
                    @endif
                    <a class="go" href="{{ route('menu') }}">See the Menu</a>
                </article>
            @endforeach
            </div>
        </div>
    </section>
@endif
