
@extends('layouts.app')

@section('title', 'Passenger Seat Restaurant | Your Food Journey')

@section('content')
<section class="hero">
    <div>
        <span class="eyebrow">✈ Your next food destination</span>

        <h1>
            A delicious journey.<br>
            <span>One destination.</span>
        </h1>

        <p>
            Discover destination-inspired Filipino flavors at Passenger Seat
            Restaurant. Gather your favorite people, share a meal, and make
            every dining experience a memorable journey.
        </p>

        <div class="hero-actions">
            <a class="btn btn-primary" href="{{ route('menu') }}">
                Explore Our Menu →
            </a>

            <a class="btn btn-secondary" href="#featured">
                Discover Our Dishes
            </a>
        </div>
    </div>

    <div class="hero-card">
        <div class="hero-plane">✈</div>
        <div class="flight-label">Your dining destination</div>
        <h2>Welcome Aboard</h2>
        <p>
            Filipino favorites, shared experiences, and a restaurant
            inspired by the joy of travel.
        </p>
        <span class="pill">Now welcoming guests</span>
    </div>
</section>

<section class="section" id="featured">
    <div class="section-heading">
        <div>
            <span class="eyebrow">The in-flight menu</span>
            <h2>Explore Our Menu</h2>
            <p>Find your next favorite dish from our available selections.</p>
        </div>

        <a class="btn btn-secondary" href="{{ route('menu') }}">
            View Full Menu →
        </a>
    </div>

    <div class="menu-grid">
        @forelse($menuItems as $item)
            <article class="menu-card">
                <div class="menu-icon">🍽️</div>

                <span class="pill">{{ $item->category }}</span>

                <h3>{{ $item->name }}</h3>

                <p>
                    {{ $item->description ?: 'A delicious choice for your dining journey.' }}
                </p>

                <div class="menu-card-bottom">
                    <span class="price">
                        ₱{{ number_format((float) $item->price, 2) }}
                    </span>

                    <span class="pill">Available</span>
                </div>
            </article>
        @empty
            <div class="empty-state">
                <h3>Our menu is preparing for takeoff.</h3>
                <p>Our featured dishes will appear here once added by the administrator.</p>
            </div>
        @endforelse
    </div>
</section>

<section class="section">
    <div class="hero-card">
        <span class="eyebrow">Gather. Share. Enjoy.</span>
        <h2>Planning a special gathering?</h2>
        <p>
            Passenger Seat is designed for shared meals, boodle fights,
            group dining, and memorable celebrations.
        </p>
        <p>
            For reservations and private-event inquiries, please contact
            the restaurant directly while online reservation functionality
            is being developed.
        </p>
    </div>
</section>
@endsection
