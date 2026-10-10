@extends('layouts.app')

@section('title', 'Passenger Seat Restaurant')

@section('content')
<section class="landing-hero" id="home">
    <div class="landing-hero-inner">
        <div class="landing-hero-copy">
            <p class="landing-eyebrow">YOUR TABLE IS BOARDING</p>
            <h1 class="landing-hero-title">HI,<br>PASSENGERS!</h1>
            <p class="landing-hero-italic">Fly and dine with us here at Passenger Seat.</p>
            <p class="landing-hero-lead">Your destination for every celebration. Your next food trip starts right here—no plane ticket needed, just take a seat.</p>
            <div class="landing-hero-actions">
                <a class="landing-button landing-button-primary" href="{{ url('/menu') }}">Explore Our Menu <span aria-hidden="true">↗</span></a>
                <a class="landing-button landing-button-ghost" href="{{ url('/events') }}">Plan an Event <span aria-hidden="true">→</span></a>
            </div>
        </div>

        <div class="landing-hero-visual">
            <img
                class="landing-hero-photo"
                src="{{ asset('images/passenger-seat-hero.jpg') }}"
                alt="Passenger Seat Restaurant dining room"
                fetchpriority="high"
                decoding="async"
            >
            <div class="landing-experience-badge">
                <small>THE PASSENGER SEAT EXPERIENCE</small>
                <strong>Gather. Share.<br>Enjoy the journey.</strong>
            </div>
        </div>
    </div>
</section>
@endsection
