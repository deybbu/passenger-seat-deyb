@extends('layouts.public-landing')

@section('title', 'Event deposit details')

@section('content')
<section id="reserve" class="landing-events-page deposit-ticket-page">
    <div class="landing-events-inner">
        <article class="event-boarding-pass deposit-boarding-pass">
            <header class="event-boarding-pass-top">
                <div class="event-boarding-brand">
                    <span class="event-boarding-mark" aria-hidden="true">&#9992;</span>
                    <span><strong>PASSENGER SEAT</strong><small>FLY AND DINE WITH US</small></span>
                </div>
                <div class="event-boarding-class">
                    <strong>EVENT DEPOSIT PASS</strong>
                    <span>PRIVATE LINK</span>
                </div>
                <div class="event-boarding-stub-brand">
                    <strong>PASSENGER SEAT</strong>
                    <span>EVENT SERVICES</span>
                </div>
            </header>

            <div class="event-boarding-pass-body">
                <main class="event-boarding-main">
                    <div class="event-boarding-eyebrow">EVENTS &amp; PRIVATE DINING</div>
                    <div class="event-boarding-destination-label">DEPOSIT DETAILS</div>
                    <h1>DEPOSIT DUE</h1>
                    <p class="event-boarding-description">This private link is valid for your Passenger Seat event proposal.</p>

                    <div class="event-boarding-details deposit-ticket-details">
                        <div><span>REFERENCE</span><strong>{{ $inquiry->reference }}</strong></div>
                        <div><span>PASSENGER</span><strong>{{ $inquiry->first_name }} {{ $inquiry->last_name }}</strong></div>
                        <div><span>EVENT</span><strong>{{ $inquiry->event_type }}</strong></div>
                        <div><span>DATE</span><strong>{{ $inquiry->event_date->format('M j, Y') }}</strong></div>
                        <div><span>PACKAGE</span><strong>{{ $inquiry->package->name }}</strong></div>
                        <div><span>GUESTS</span><strong>{{ $inquiry->guest_count }} PAX</strong></div>
                    </div>

                    <div class="event-boarding-notes deposit-pricing">
                        <span>PACKAGE SUBTOTAL</span><strong>&#8369;{{ number_format($subtotal, 2) }}</strong>
                        <span>SERVICE CHARGE</span><strong>&#8369;{{ number_format($service, 2) }}</strong>
                        <span>TAX</span><strong>&#8369;{{ number_format($tax, 2) }}</strong>
                        <span>ESTIMATED TOTAL</span><strong>&#8369;{{ number_format($total, 2) }}</strong>
                        <span>50% RESERVATION DEPOSIT</span><strong class="deposit-due">&#8369;{{ number_format($deposit, 2) }}</strong>
                    </div>
                </main>

                <aside class="event-boarding-stub">
                    <span class="event-boarding-destination-label">YOUR DESTINATION</span>
                    <strong class="event-boarding-stub-destination">{{ $inquiry->event_type }}</strong>
                    <span class="event-boarding-barcode" aria-hidden="true"></span>
                    <div class="event-boarding-stub-details">
                        <span>PASSENGER<strong>{{ $inquiry->first_name }} {{ $inquiry->last_name }}</strong></span>
                        <span>DATE<strong>{{ $inquiry->event_date->format('M j, Y') }}</strong></span>
                        <span>PACKAGE<strong>{{ $inquiry->package->name }}</strong></span>
                        <span>GUESTS<strong>{{ $inquiry->guest_count }} PAX</strong></span>
                        <span>REFERENCE<strong>{{ $inquiry->reference }}</strong></span>
                        <span>DEPOSIT DUE<strong class="deposit-due">&#8369;{{ number_format($deposit, 2) }}</strong></span>
                    </div>
                </aside>
            </div>

            <div class="deposit-ticket-contact">
                <strong>PAYMENT ARRANGEMENTS</strong>
                <span>To arrange payment, include reference {{ $inquiry->reference }}.
                    @if (config('mail.from.address'))
                        Contact Passenger Seat at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
                    @else
                        Contact Passenger Seat and provide your reference number.
                    @endif
                    Your date remains provisional until the restaurant confirms the booking.
                </span>
            </div>

            <footer class="event-boarding-pass-footer">
                <span>YOUR CELEBRATION STARTS HERE</span>
                <span>PS&nbsp; {{ $inquiry->reference }}</span>
            </footer>
        </article>
    </div>
</section>
@endsection
