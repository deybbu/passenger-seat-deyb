
@extends('layouts.app')

@section('title', 'Your Next Culinary Destination')

@section('content')

{{-- HERO SECTION --}}
<section class="hero" id="home">
    <div class="hero-glow"></div>

    <div class="site-container hero-grid">
        <div class="hero-copy">
            <div class="eyebrow">
                <span class="eyebrow-line"></span>
                YOUR TABLE IS BOARDING
            </div>

            <h1>
                HI, PASSENGERS!<br>
                <em>Fly and dine with us here at Passenger Seat.</em>
            </h1>

            <p class="hero-lead">
                Your destination for every celebration. Your next food trip starts right here—no plane ticket needed, just take a seat.
            </p>

            <div class="hero-actions">
                <a class="button" href="#menu">
                    Explore Our Menu <span aria-hidden="true">↗</span>
                </a>

                <a class="text-button" href="#experience">
                    Discover the Experience <span aria-hidden="true">→</span>
                </a>
            </div>

            <div class="hero-meta">
                <div class="meta-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M3 14.5 21 4l-7 17-3.5-6.5L3 14.5Z"
                              stroke="currentColor"
                              stroke-width="1.6"
                              stroke-linejoin="round"/>
                        <path d="m10.5 14.5 4-4"
                              stroke="currentColor"
                              stroke-width="1.6"/>
                    </svg>
                </div>

                <div>
                    <strong>A different kind of dining journey</strong>
                    <span>Located at SM City San Mateo, Rizal</span>
                </div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-image-frame">
                <img
                    src="{{ asset('images/passenger-seat-hero.jpg') }}"
                    alt="A table of colorful dishes at Passenger Seat Restaurant"
                    fetchpriority="high"
                    decoding="async"
                    onerror="this.onerror=null;this.src='{{ asset('images/passenger-seat-experience.jpg') }}';"
                >

                <div class="image-shade"></div>

                <div class="image-label">
                    <span class="label-dot"></span>
                    A JOURNEY IN EVERY BITE
                </div>
            </div>

            <div class="floating-card">
                <span class="floating-kicker">
                    THE PASSENGER SEAT EXPERIENCE
                </span>

                <strong>
                    Gather. Share.<br>
                    Enjoy the journey.
                </strong>

                <span class="floating-route">
                    <i></i> GOOD FOOD · GOOD COMPANY
                </span>
            </div>

            <svg class="flight-path"
                 viewBox="0 0 180 90"
                 fill="none"
                 aria-hidden="true">
                <path d="M4 78C42 78 37 12 83 12s42 63 91 25"
                      stroke="#C62828"
                      stroke-width="1.5"
                      stroke-dasharray="4 5"/>
                <path d="m164 35 10 2-3 10"
                      stroke="#C62828"
                      stroke-width="1.5"
                      stroke-linecap="round"
                      stroke-linejoin="round"/>
            </svg>
        </div>
    </div>

    <div class="hero-bottom-line"></div>
</section>


{{-- INTRODUCTION STRIP --}}
<section class="intro-strip">
    <div class="site-container intro-strip-inner">
        <span>THE DESTINATION</span>

        <p>
            Filipino-inspired food. Shared moments. A memorable dining experience.
        </p>

        <span class="strip-number">01 / EXPERIENCE</span>
    </div>
</section>


{{-- MENU SECTION --}}
<section class="section menu-section" id="menu">
    <div class="site-container">

        <div class="section-heading">
            <div>
                <div class="eyebrow">
                    <span class="eyebrow-line"></span>
                    THE ONBOARD MENU
                </div>

                <h2>
                    Good food, <em>great journeys.</em>
                </h2>
            </div>

            <p>
                Explore the menu and discover something to share around the table.
            </p>
        </div>


        {{-- MENU CATEGORIES --}}
        @if(isset($categories) && $categories->isNotEmpty())
            <div class="category-list" aria-label="Menu categories">
                @foreach($categories as $category)
                    <span class="category-chip">
                        {{ $category->name }}
                    </span>
                @endforeach
            </div>
        @endif


        {{-- FEATURED MENU ITEMS --}}
        @if(isset($featuredItems) && $featuredItems->isNotEmpty())
            <div class="menu-grid">

                @foreach($featuredItems as $item)
                    <article class="menu-card">

                        <div class="menu-image">

                            @if($item->image_path)
                                <img
                                    src="{{ \Illuminate\Support\Facades\Storage::url($item->image_path) }}"
                                    alt="{{ $item->name }}"
                                    loading="lazy"
                                    decoding="async"
                                    onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';"
                                >

                                <div class="menu-image-placeholder"
                                     style="display:none;">
                                    <span>PASSENGER SEAT</span>
                                    <strong>{{ $item->name }}</strong>
                                </div>
                            @else
                                <div class="menu-image-placeholder">
                                    <span>PASSENGER SEAT</span>
                                    <strong>{{ $item->name }}</strong>
                                </div>
                            @endif

                            <span class="available-badge">
                                <i></i> Available
                            </span>
                        </div>


                        <div class="menu-card-content">

                            <span class="menu-category">
                                {{ $item->category?->name ?? 'Restaurant favorite' }}
                            </span>

                            <h3>{{ $item->name }}</h3>

                            <p>
                                {{ $item->description ?: 'A delicious choice for your next dining journey.' }}
                            </p>

                            <div class="menu-card-bottom">
                                <strong>
                                    ₱{{ number_format((float) $item->price, 2) }}
                                </strong>

                                <span>Featured selection</span>
                            </div>

                        </div>
                    </article>
                @endforeach

            </div>

        @else

            {{-- EMPTY MENU STATE --}}
            <div class="empty-menu">
                <div class="empty-icon" aria-hidden="true">✳</div>

                <h3>Your menu journey starts here.</h3>

                <p>
                    Featured dishes will appear here once menu items are added by the restaurant team.
                </p>

                <span>
                    MENU ITEMS WILL BE MANAGED THROUGH THE ADMIN SYSTEM IN A LATER PHASE
                </span>
            </div>

        @endif

    </div>
</section>


{{-- EXPERIENCE SECTION --}}
<section class="experience-section" id="experience">
    <div class="site-container experience-grid">

        <div class="experience-visual">
            <img
                src="{{ asset('images/passenger-seat-experience.jpg') }}"
                alt="Passenger Seat Restaurant dining experience"
                loading="lazy"
                decoding="async"
                onerror="this.onerror=null;this.src='{{ asset('images/passenger-seat-hero.jpg') }}';"
            >

            <div class="experience-stamp">
                <span>YOUR</span>
                <strong>SEAT</strong>
                <span>AWAITS</span>
            </div>
        </div>


        <div class="experience-copy">

            <div class="eyebrow">
                <span class="eyebrow-line"></span>
                MORE THAN A MEAL
            </div>

            <h2>
                Come for the food.<br>
                <em>Stay for the moments.</em>
            </h2>

            <p>
                Passenger Seat brings an aviation-inspired dining concept together with Filipino-inspired flavors. Whether you're catching up with family, sharing a meal with friends, or planning a group gathering, there's room at the table for a journey worth remembering.
            </p>


            <div class="experience-points">

                <div>
                    <span class="point-number">01</span>

                    <div>
                        <strong>Everyday dining</strong>
                        <p>Make a regular meal feel like a little getaway.</p>
                    </div>
                </div>

                <div>
                    <span class="point-number">02</span>

                    <div>
                        <strong>Meals made to share</strong>
                        <p>Enjoy group meals and boodle-fight-style gatherings.</p>
                    </div>
                </div>

                <div>
                    <span class="point-number">03</span>

                    <div>
                        <strong>Dine-in or take-out</strong>
                        <p>Choose a dining option that suits your plans.</p>
                    </div>
                </div>

            </div>

            <a class="text-button red-text" href="{{ route('story') }}">
                Discover our concept <span aria-hidden="true">→</span>
            </a>

        </div>
    </div>
</section>


{{-- LOCATION SECTION --}}
<section class="location-section">
    <div class="site-container location-card">

        <div class="location-copy">

            <div class="eyebrow">
                <span class="eyebrow-line"></span>
                YOUR DESTINATION
            </div>

            <h2>
                Meet us at<br>
                <em>Passenger Seat.</em>
            </h2>

            <p>
                Find us on the third floor of SM City San Mateo, Rizal.
            </p>

            <a class="button button-light" href="{{ route('contact') }}">
                Plan Your Visit <span aria-hidden="true">↗</span>
            </a>

        </div>


        <div class="location-art" aria-hidden="true">

            <div class="orbit orbit-one"></div>
            <div class="orbit orbit-two"></div>

            <div class="location-pin">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"
                          stroke="currentColor"
                          stroke-width="1.5"/>

                    <circle cx="12"
                            cy="10"
                            r="2.5"
                            stroke="currentColor"
                            stroke-width="1.5"/>
                </svg>
            </div>

            <span class="map-label map-label-one">SM CITY</span>

            <span class="map-label map-label-two">
                SAN MATEO · RIZAL
            </span>

            <span class="map-coordinate">
                14°40' N / 121°07' E
            </span>

        </div>
    </div>
</section>
<style>
.location-card {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    background:
        linear-gradient(
            90deg,
            rgba(155, 20, 32, 0.92) 0%,
            rgba(155, 20, 32, 0.72) 30%,
            rgba(155, 20, 32, 0.35) 60%,
            rgba(155, 20, 32, 0.08) 100%
        ),
        url("{{ asset('images/thirdfloor.jpg') }}")
        center / cover no-repeat;
    border-radius: 24px;
}

.location-copy {
    position: relative;
    z-index: 2;
    color: #ffffff;
}

.location-copy .eyebrow {
    color: #ffe2e2;
}

.location-copy h2 {
    color: #ffffff;
}

.location-copy h2 em {
    color: #ffd0d0;
}

.location-copy p {
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.8;
}

.location-copy .button-light {
    background: #ffffff;
    color: #a91f2b;
    border: 1px solid #ffffff;
    box-shadow: 0 8px 22px rgba(80, 0, 0, 0.18);
}

.location-copy .button-light:hover {
    background: #ffe8e8;
    color: #801722;
}

.location-art {
    position: relative;
    z-index: 1;
}

@media (max-width: 768px) {
    .location-card {
        background:
            linear-gradient(
                180deg,
                rgba(155, 20, 32, 0.9) 0%,
                rgba(155, 20, 32, 0.65) 40%,
                rgba(155, 20, 32, 0.15) 100%
            ),
            url("{{ asset('images/passenger-seat-experience.jpg') }}")
            center / cover no-repeat;
    }
}
</style>
@endsection
