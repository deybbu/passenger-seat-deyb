@extends('layouts.app')

@section('title', 'Events & Private Dining')

@section('content')
<section class="landing-events-page">
    <div class="landing-events-inner">
        @include('landing.partials.event-boarding-pass', [
            'headingLevel' => 'h1',
            'description' => 'Pick an available date, then tell us about your event.',
        ])

        @if ($confirmation)
            <section class="landing-event-confirmation" role="status" aria-live="polite">
                <span class="landing-event-confirmation-icon" aria-hidden="true">✓</span>
                <h2>Inquiry Received</h2>
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

                @if ($errors->has('inquiry'))
                    <p class="landing-event-error" role="alert">{{ $errors->first('inquiry') }}</p>
                @endif

                <section class="landing-event-card">
                    <h2>1. Event Availability Calendar</h2>
                    <div class="landing-event-legend" aria-label="Calendar legend">
                        <span><i class="is-available" aria-hidden="true"></i>Available</span>
                        <span><i class="is-pending" aria-hidden="true"></i>Pending</span>
                        <span><i class="is-booked" aria-hidden="true"></i>Booked</span>
                        <span><i class="is-blocked" aria-hidden="true"></i>Unavailable</span>
                    </div>

                    <div
                        class="landing-event-calendar"
                        data-event-calendar
                        data-today="{{ $today }}"
                        data-availability="{{ json_encode($availability) }}"
                    >
                        <div class="landing-calendar-header">
                            <button type="button" data-calendar-previous aria-label="Previous month">‹</button>
                            <h3 data-calendar-month aria-live="polite"></h3>
                            <button type="button" data-calendar-next aria-label="Next month">›</button>
                        </div>
                        <div class="landing-calendar-grid" data-calendar-grid role="grid" aria-label="Event availability"></div>
                        <p class="landing-event-note">
                            Pending inquiries, confirmed bookings, and dates blocked by the restaurant are shown here.
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
                        <input id="event-date" type="hidden" name="event_date" value="{{ old('event_date') }}" data-ticket-input="date">
                        <p class="landing-event-error" id="event-date-error" role="alert">
                            {{ $errors->first('event_date') }}
                        </p>
                    </div>
                    <noscript><p class="landing-event-error">JavaScript is required to choose an available date on the calendar.</p></noscript>
                </section>

                <section class="landing-event-card landing-event-packages-card" aria-labelledby="landing-event-packages-title">
                    <h2 id="landing-event-packages-title">Event Packages</h2>
                    <p class="event-capacity-note">Restaurant capacity is 30 guests maximum. Choose a package sized for your guest count.</p>
                    <div class="event-tier-list" aria-label="Available event packages">
                        @foreach ($eventPackages as $package)
                            <article class="event-tier-card">
                                <h3>{{ $package->name }}</h3>
                                <strong>Good for {{ $package->min_guests }}–{{ $package->max_guests }} pax</strong>
                                <p>{{ $package->description }}</p>
                                <b>₱{{ number_format((float) $package->fixed_price, 2) }} fixed tier price</b>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="landing-event-card">
                    <h2>2. Contact Information</h2>
                    <div class="landing-event-fields">
                        <div>
                            <label for="first-name">First Name <i>*</i></label>
                            <input id="first-name" name="first_name" autocomplete="given-name" value="{{ old('first_name') }}" required maxlength="100" data-ticket-input="first_name" @if ($errors->has('first_name')) aria-invalid="true" @endif>
                            @error('first_name')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="last-name">Last Name <i>*</i></label>
                            <input id="last-name" name="last_name" autocomplete="family-name" value="{{ old('last_name') }}" required maxlength="100" data-ticket-input="last_name" @if ($errors->has('last_name')) aria-invalid="true" @endif>
                            @error('last_name')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email">Email Address <i>*</i></label>
                            <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required maxlength="255" data-ticket-input="email" @if ($errors->has('email')) aria-invalid="true" @endif>
                            @error('email')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="mobile-number">Mobile Number <i>*</i></label>
                            <input id="mobile-number" name="mobile_number" type="tel" autocomplete="tel" placeholder="09XX XXX XXXX" value="{{ old('mobile_number') }}" required maxlength="30" data-ticket-input="mobile_number" @if ($errors->has('mobile_number')) aria-invalid="true" @endif>
                            @error('mobile_number')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="landing-event-card">
                    <h2>3. Event Requirements</h2>
                    <div class="landing-event-fields">
                        <div>
                            <label for="event-type">Event Type <i>*</i></label>
                            <select id="event-type" name="event_type" required data-ticket-input="event_type" @if ($errors->has('event_type')) aria-invalid="true" @endif>
                                <option value="">Select event type</option>
                                @foreach ($eventTypes as $eventType)
                                    <option value="{{ $eventType }}" @selected(old('event_type') === $eventType)>{{ $eventType }}</option>
                                @endforeach
                            </select>
                            @error('event_type')<p class="landing-event-error">{{ $message }}</p>@enderror
                            <div class="landing-event-other-type" data-other-event-field @if (old('event_type') !== 'Other') hidden @endif>
                                <label for="event-type-other">Please Specify Your Event Type <i>*</i></label>
                                <input id="event-type-other" name="event_type_other" value="{{ old('event_type_other') }}" maxlength="120" data-ticket-input="event_type_other" @if (old('event_type') === 'Other') required @endif @if ($errors->has('event_type_other')) aria-invalid="true" @endif>
                                @error('event_type_other')<p class="landing-event-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="landing-event-time-range">
                            <label for="preferred-time-from">Preferred Time <i>*</i></label>
                            <div class="landing-event-time-selects">
                                <label for="preferred-time-from">From
                                    <select id="preferred-time-from" name="preferred_time_from" required data-ticket-input="preferred_time_from" @if ($errors->has('preferred_time_from')) aria-invalid="true" @endif>
                                        <option value="">Start Time</option>
                                        @foreach ($preferredTimes as $preferredTime)
                                            <option value="{{ $preferredTime }}" @selected(old('preferred_time_from') === $preferredTime)>{{ $preferredTime }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label for="preferred-time-to">To
                                    <select id="preferred-time-to" name="preferred_time_to" required data-ticket-input="preferred_time_to" @if ($errors->has('preferred_time_to')) aria-invalid="true" @endif>
                                        <option value="">End Time</option>
                                        @foreach ($preferredTimes as $preferredTime)
                                            <option value="{{ $preferredTime }}" @selected(old('preferred_time_to') === $preferredTime)>{{ $preferredTime }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            @error('preferred_time_from')<p class="landing-event-error">{{ $message }}</p>@enderror
                            @error('preferred_time_to')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="guest-count">Number of Guests (Pax) <i>*</i></label>
                            <input id="guest-count" name="guest_count" type="number" min="1" max="30" step="1" inputmode="numeric" value="{{ old('guest_count') }}" required data-ticket-input="guest_count" @if ($errors->has('guest_count')) aria-invalid="true" @endif>
                            @error('guest_count')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="event-package">Package</label>
                            <select id="event-package" name="event_package_id" data-ticket-input="event_package_id">
                                <option value="" data-placeholder>Select guest count first</option>
                                @foreach ($eventPackages as $package)
                                    <option value="{{ $package->id }}" data-name="{{ $package->name }}" data-min-guests="{{ $package->min_guests }}" data-max-guests="{{ $package->max_guests }}" @selected((string) old('event_package_id') === (string) $package->id)>{{ $package->name }} ({{ $package->min_guests }}–{{ $package->max_guests }} pax) — ₱{{ number_format((float) $package->fixed_price, 2) }}</option>
                                @endforeach
                            </select>
                            <small id="package-capacity-hint">Enter the guest count to see eligible packages.</small>
                            @error('event_package_id')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="is-full-width">
                            <label for="dietary-restrictions">Food Preferences / Dietary Restrictions</label>
                            <textarea id="dietary-restrictions" name="dietary_restrictions" maxlength="5000" data-ticket-input="dietary_restrictions">{{ old('dietary_restrictions') }}</textarea>
                            @error('dietary_restrictions')<p class="landing-event-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="is-full-width">
                            <label for="special-requests">Special Requests / Message</label>
                            <textarea id="special-requests" name="special_requests" maxlength="5000" data-ticket-input="special_requests">{{ old('special_requests') }}</textarea>
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
