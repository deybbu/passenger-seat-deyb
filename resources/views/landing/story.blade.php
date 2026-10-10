@extends('layouts.app')
@section('title', 'Our Story')

@section('content')
<section class="landing-story-page">
    <div class="landing-story-inner">
        <header class="landing-story-heading">
            <p class="landing-eyebrow">CULINARY PHILOSOPHY &amp; CONCEPT</p>
            <h1>The story behind <em>Passenger Seat.</em></h1>
            <p>Why a restaurant that feels like a flight, and the people behind it.</p>
        </header>

        <p class="landing-story-note" role="note">
            Placeholder text. Replace each section with the owner's own words and photos.
        </p>

        <div class="landing-story-grid" id="experience">
            <aside class="landing-story-owner">
                <div class="landing-story-owner-photo" role="img" aria-label="Owner photo placeholder">
                    Owner photo placeholder
                </div>
                <strong>Mariae</strong>
                <span>Owner</span>
            </aside>

            <div class="landing-story-sections">
                <x-landing.story-section
                    title="The Meaning / Backstory of Passenger Seat"
                    description="Explain what the name means and why the dining room is built like an airplane cabin."
                />
                <x-landing.story-section
                    title="Early Roots"
                    description="Share where Mariae grew up and the food memories that shaped her cooking."
                />
                <x-landing.story-section
                    title="Global Maritime Background"
                    description="Describe the years spent working at sea and the places and cuisines that inspired the menu."
                />
                <x-landing.story-section
                    title="The Turning Point"
                    description="Tell the moment that led to opening Passenger Seat."
                />
                <x-landing.story-section
                    title="The Vision"
                    description="Describe what guests should feel and what the restaurant hopes to become."
                />
            </div>
        </div>
    </div>
</section>
@endsection
