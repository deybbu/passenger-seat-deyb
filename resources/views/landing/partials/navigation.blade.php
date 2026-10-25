<nav id="nav" @class(['sc' => request()->routeIs('events.deposit')]) data-solid-nav="{{ request()->routeIs('events.deposit') ? 'true' : 'false' }}">
    <div class="wrap nv">
        <a href="#home" class="logo" aria-label="Passenger Seat home">
            <img class="lg" alt="Passenger Seat logo" src="{{ asset('images/landing/passenger-seat-logo.png') }}">
        </a>
        <div class="links" id="links">
            <a href="#menu">Menu</a>
            <a href="#story">Our Story</a>
            <a href="#experience">Experience</a>
            <a href="#reserve">Events</a>
            <a href="#contact">Contact</a>
        </div>
        <div class="act">
            <button class="theme-toggle" id="theme" type="button" role="switch" aria-checked="false" aria-label="Switch to dark mode" title="Switch to dark mode">
                <span class="theme-knob" aria-hidden="true">
                    <svg class="theme-icon-sun" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l-1.42-1.42m11.3-11.3 1.42-1.42"/>
                    </svg>
                    <svg class="theme-icon-moon" viewBox="0 0 24 24">
                        <path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z"/>
                    </svg>
                </span>
            </button>
            <button class="btn burger" id="burger" type="button" aria-label="Menu" aria-expanded="false">&#9776;</button>
        </div>
    </div>
</nav>
