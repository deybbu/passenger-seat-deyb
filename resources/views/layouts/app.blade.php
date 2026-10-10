<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/passenger seat favicon.png') }}">
    <title>@yield('title', 'Passenger Seat Restaurant') | Passenger Seat</title>
    <meta name="description" content="A Filipino-inspired dining experience at Passenger Seat Restaurant.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <nav class="nav-shell site-container" aria-label="Main navigation">
            <a class="brand" href="{{ route('home') }}" aria-label="Passenger Seat home">
                <span class="brand-mark" aria-hidden="true">PS</span>
                <span class="brand-copy"><strong>PASSENGER SEAT</strong><small>RESTAURANT</small></span>
            </a>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
                <span></span><span></span><span></span><span class="sr-only">Toggle navigation</span>
            </button>
            <div class="nav-links" id="primary-nav">
                <a href="{{ route('home') }}#home">Home</a>
                <a href="{{ route('home') }}#menu">Menu</a>
                <a href="{{ route('story') }}">Our Story</a>
                <a href="{{ route('home') }}#experience">Experience</a>
                <a href="{{ route('contact') }}">Contact</a>
                <a class="button button-small" href="{{ route('home') }}#menu">Explore Menu <span aria-hidden="true">↗</span></a>
            </div>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="site-container footer-grid">
            <div>
                <a class="brand brand-footer" href="{{ route('home') }}">
                    <span class="brand-mark" aria-hidden="true">PS</span>
                    <span class="brand-copy"><strong>PASSENGER SEAT</strong><small>RESTAURANT</small></span>
                </a>
                <p class="footer-note">A dining destination inspired by travel and Filipino flavors.</p>
            </div>
            <div>
                <h3>Explore</h3>
                <a href="{{ route('home') }}#menu">Menu</a>
                <a href="{{ route('story') }}">Our Story</a>
                <a href="{{ route('home') }}#experience">Dining Experience</a>
            </div>
            <div>
                <h3>Visit Us</h3>
                <p>3rd Floor, SM City San Mateo<br>Rizal, Philippines</p>
                <p class="muted">Please contact the restaurant for current hours and contact details.</p>
            </div>
        </div>
        <div class="site-container footer-bottom">
            <span>© {{ date('Y') }} Passenger Seat Restaurant</span>
            <span>Made for a memorable journey.</span>
        </div>
    </footer>
</body>
</html>
