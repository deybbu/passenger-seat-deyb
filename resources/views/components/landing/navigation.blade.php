<header class="landing-navigation">
    <nav class="landing-nav-shell" aria-label="Main navigation">
        <a class="landing-brand" href="{{ route('home') }}" aria-label="Passenger Seat home">
            <span class="landing-brand-mark" aria-hidden="true">PS</span>
            <span class="landing-brand-copy"><strong>PASSENGER SEAT</strong><small>RESTAURANT</small></span>
        </a>

        <button class="nav-toggle landing-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Toggle navigation">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>

        <div class="nav-links landing-nav-links" id="primary-nav">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ url('/menu') }}">Menu</a>
            <a href="{{ url('/story') }}">Our Story</a>
            <a href="{{ url('/story#experience') }}">Experience</a>
            <a href="{{ url('/events') }}">Events</a>
            <a href="#visit">Contact</a>
            <button
                class="landing-theme-toggle"
                type="button"
                role="switch"
                data-theme-toggle
                aria-checked="false"
                aria-label="Switch to dark mode"
            >
                <span class="landing-theme-knob" aria-hidden="true">
                    <svg class="theme-icon-moon" viewBox="0 0 24 24">
                        <path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z"/>
                    </svg>
                    <svg class="theme-icon-sun" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l-1.42-1.42m11.3-11.3 1.42-1.42"/>
                    </svg>
                </span>
            </button>
            <a class="landing-button landing-button-primary landing-nav-cta" href="{{ url('/menu') }}">Explore Menu <span aria-hidden="true">↗</span></a>
        </div>
    </nav>
</header>
