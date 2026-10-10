@extends('layouts.app')

@section('title', 'Events & Private Dining')

@section('content')
<section class="landing-events-page">
    <div class="landing-events-inner">
        <header class="landing-events-heading">
            <p class="landing-eyebrow">EVENTS &amp; PRIVATE DINING</p>
            <h1>Check Event Availability</h1>
            <p>Pick an available date, then tell us about your event.</p>
        </header>

        @if ($confirmation)
            <section class="landing-event-confirmation" role="status" aria-live="polite">
                <span class="landing-event-confirmation-icon" aria-hidden="true">✓</span>
                <h2>Inquiry received</h2>
                <p>Our team will review your request. Your date is not confirmed yet.</p>

                <dl class="landing-event-summary">
                    <div><dt>Reference</dt><dd>{{ $confirmation['reference'] }}</dd></div>
                    <div>
                        <dt>Date</dt>
                        <dd>{{ \Illuminate\Support\Carbon::parse($confirmation['event_date'])->translatedFormat('l, F j, Y') }}</dd>
                    </div>
                    <div>
                        <dt>Event</dt>
                        <dd>{{ $confirmation['event_type'] }}, {{ $confirmation['guest_count'] }} guests</dd>
                    </div>
                    <div><dt>Request status</dt><dd><span class="landing-event-pill">Pending review</span></dd></div>
                    <div><dt>Customer notification</dt><dd><span class="landing-event-pill">Not yet sent</span></dd></div>
                </dl>

                <a class="landing-button" href="{{ route('events') }}">Send another inquiry</a>
            </section>
        @else
            <form class="landing-event-form" action="{{ route('events.store') }}" method="POST" novalidate>
                @csrf

                <section class="landing-event-card">
                    <h2>1. Event Availability Calendar</h2>
                    <div class="landing-event-legend" aria-label="Calendar legend">
                        <span><i class="is-available" aria-hidden="true"></i>Available</span>
                        <span><i class="is-pending" aria-hidden="true"></i>Pending</span>
                        <span><i class="is-booked" aria-hidden="true"></i>Booked</span>
                    </div>

                    <div
                        class="landing-event-calendar"
                        data-event-calendar
                        data-today="{{ $today }}"
                        data-availability='@js($availability)'
                    >
                        <div class="landing-calendar-header">
                            <button type="button" data-calendar-previous aria-label="Previous month">‹</button>
                            <h3 data-calendar-month aria-live="polite"></h3>
                            <button type="button" data-calendar-next aria-label="Next month">›</button>
                        </div>
                        <div class="landing-calendar-grid" data-calendar-grid role="grid" aria-label="Event availability"></div>
                        <p class="landing-event-note">
                            Sample calendar only. Actual availability must come from the restaurant's reservation records.
                        </p>
                    </div>

                    <div class="landing-event-date-field">
                        <label for="event-date-display">Selected Event Date <i>*</i></label>
                        <input
                            id="event-date-display"
                            type="text"
                            value="{{ old('event_date') ? \Illuminate\Support\Carbon::parse(old('event_date'))->translatedFormat('l, F j, Y') : '' }}"
                            placeholder="Select an available date"
                            readonly
                            aria-describedby="event-date-error"
                            @if ($errors->has('event_date')) aria-invalid="true" @endif
                        >
                        <input id="event-date" type="hidden" name="event_date" value="{{ old('event_date') }}">
                        <p class="landing-event-error" id="event-date-error" role="alert">
                            {{ $errors->first('event_date') }}
                        </p>
                    </div>
                    <noscript><p class="landing-event-error">JavaScript is required to choose an available date on the calendar.</p></noscript>
                </section>

                <section class="landing-event-card">
                    <h2>2. Contact Information</h2>
                    <div class="landing-event-fields">
                        <div>
                            <label for="first-name">First Name <i>*</i></label>
                            <input id="first-name" name="first_name" autocomplete="given-name" value="{{ old('first_name') }}" required maxlength="100" @if ($errors->has('first_name')) aria-invalid="true" @endif>
                            @error('first_name')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="last-name">Last Name <i>*</i></label>
                            <input id="last-name" name="last_name" autocomplete="family-name" value="{{ old('last_name') }}" required maxlength="100" @if ($errors->has('last_name')) aria-invalid="true" @endif>
                            @error('last_name')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email">Email Address <i>*</i></label>
                            <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required maxlength="255" @if ($errors->has('email')) aria-invalid="true" @endif>
                            @error('email')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="mobile-number">Mobile Number <i>*</i></label>
                            <input id="mobile-number" name="mobile_number" type="tel" autocomplete="tel" placeholder="09XX XXX XXXX" value="{{ old('mobile_number') }}" required maxlength="30" @if ($errors->has('mobile_number')) aria-invalid="true" @endif>
                            @error('mobile_number')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="landing-event-card">
                    <h2>3. Event Requirements</h2>
                    <div class="landing-event-fields">
                        <div>
                            <label for="event-type">Event Type <i>*</i></label>
                            <select id="event-type" name="event_type" required @if ($errors->has('event_type')) aria-invalid="true" @endif>
                                <option value="">Select event type</option>
                                @foreach ($eventTypes as $eventType)
                                    <option value="{{ $eventType }}" @selected(old('event_type') === $eventType)>{{ $eventType }}</option>
                                @endforeach
                            </select>
                            @error('event_type')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="preferred-time">Preferred Time <i>*</i></label>
                            <select id="preferred-time" name="preferred_time" required @if ($errors->has('preferred_time')) aria-invalid="true" @endif>
                                <option value="">Select preferred time</option>
                                @foreach ($preferredTimes as $preferredTime)
                                    <option value="{{ $preferredTime }}" @selected(old('preferred_time') === $preferredTime)>{{ $preferredTime }}</option>
                                @endforeach
                            </select>
                            @error('preferred_time')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="guest-count">Number of Guests (Pax) <i>*</i></label>
                            <input id="guest-count" name="guest_count" type="number" min="1" step="1" inputmode="numeric" value="{{ old('guest_count') }}" required @if ($errors->has('guest_count')) aria-invalid="true" @endif>
                            @error('guest_count')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="preferred-package">Preferred Package / Budget</label>
                            <select id="preferred-package" name="preferred_package">
                                <option value="">Not sure yet</option>
                                @foreach ($packageOptions as $packageOption)
                                    <option value="{{ $packageOption }}" @selected(old('preferred_package') === $packageOption)>{{ $packageOption }}</option>
                                @endforeach
                            </select>
                            @error('preferred_package')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="is-full-width">
                            <label for="dietary-restrictions">Food Preferences / Dietary Restrictions</label>
                            <textarea id="dietary-restrictions" name="dietary_restrictions" maxlength="5000">{{ old('dietary_restrictions') }}</textarea>
                            @error('dietary_restrictions')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="is-full-width">
                            <label for="special-requests">Special Requests / Message</label>
                            <textarea id="special-requests" name="special_requests" maxlength="5000">{{ old('special_requests') }}</textarea>
                            @error('special_requests')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <p class="landing-event-warning">
                        <span aria-hidden="true">ⓘ</span>
                        Submitting an inquiry does not automatically confirm your event booking.
                    </p>
                    <button class="landing-button landing-button-primary landing-event-submit" type="submit">Submit inquiry</button>
                </section>
            </form>
        @endif
    </div>
</section>
@endsection

@push('scripts')
    @vite('resources/js/landing-events.js')
@endpush
