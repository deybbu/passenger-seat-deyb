<section id="reserve" class="landing-solari-event">
    <div class="wrap">
        @include('landing.partials.event-boarding-pass', [
            'headingLevel' => 'h2',
            'description' => 'Birthdays, christenings, corporate events and whole-restaurant rental. Pick an available date, then tell us about your event.',
        ])

        <div class="evg">
            <div class="card landing-solari-event-card">
                <h3 class="h3">1. Event Availability Calendar</h3>
                <div class="legend" aria-label="Legend">
                    <span><i class="dot a" aria-hidden="true"></i>Available</span>
                    <span><i class="dot p" aria-hidden="true"></i>Pending</span>
                    <span><i class="dot b" aria-hidden="true"></i>Booked</span>
                    <span><i class="dot b" aria-hidden="true"></i>Unavailable</span>
                </div>

                <div
                    class="landing-event-calendar"
                    data-event-calendar
                    data-today="{{ $today }}"
                    data-availability="{{ json_encode($availability) }}"
                >
                    <div class="cn">
                        <button type="button" class="btn sm" data-calendar-previous aria-label="Previous month">&lsaquo;</button>
                        <b data-calendar-month aria-live="polite"></b>
                        <button type="button" class="btn sm" data-calendar-next aria-label="Next month">&rsaquo;</button>
                    </div>
                    <div class="cgrid" data-calendar-grid role="grid" aria-label="Event availability"></div>
                    <p class="note">Pending inquiries are not confirmed bookings, but the requested date is held while the inquiry is reviewed.</p>
                </div>

                <div>
                    <label for="event-date-display">Selected event date <i>*</i></label>
                    <input
                        id="event-date-display"
                        type="text"
                        value="{{ old('event_date') ? \Illuminate\Support\Carbon::parse(old('event_date'))->translatedFormat('l, F j, Y') : '' }}"
                        placeholder="Select a date on the calendar"
                        readonly
                        aria-describedby="event-date-error"
                        @if ($errors->has('event_date')) aria-invalid="true" @endif
                    >
                    <input id="event-date" type="hidden" name="event_date" form="event-inquiry-form" value="{{ old('event_date') }}" data-ticket-input="date">
                    <p class="err" id="event-date-error" role="alert">{{ $errors->first('event_date') }}</p>
                </div>
                <noscript><p class="event-error">JavaScript is required to choose an available date on the calendar.</p></noscript>

                <section class="card landing-solari-event-card landing-event-packages-card" aria-labelledby="landing-event-packages-title">
                    <h3 class="h3" id="landing-event-packages-title">Event Packages</h3>
                    <p class="event-capacity-note">Restaurant capacity is 30 guests maximum. Choose a package sized for your guest count.</p>
                    <div class="event-tier-list" aria-label="Available event packages">
                        @foreach ($eventPackages as $package)
                            <article class="event-tier-card">
                                <h4>{{ $package->name }}</h4>
                                <strong>Good for {{ $package->min_guests }}–{{ $package->max_guests }} pax</strong>
                                <p>{{ $package->description }}</p>
                                <b>₱{{ number_format((float) $package->fixed_price, 2) }} fixed tier price</b>
                            </article>
                        @endforeach
                    </div>
                </section>
            </div>

            @if ($confirmation)
                <section class="card event-confirmation landing-solari-event-card" role="status" aria-live="polite">
                    <span class="event-confirmation-icon" aria-hidden="true">✓</span>
                    <h3 class="h3">Inquiry Received</h3>
                    <p>Our team will review your request. Your date is not confirmed yet.</p>
                    <div class="sum">
                        <div><span>Reference</span><b>{{ $confirmation['reference'] }}</b></div>
                        <div>
                            <span>Date</span>
                            <b>{{ \Illuminate\Support\Carbon::parse($confirmation['event_date'])->translatedFormat('l, F j, Y') }}</b>
                        </div>
                        <div><span>Event</span><b>{{ $confirmation['event_type'] }}, {{ $confirmation['guest_count'] }} guests</b></div>
                        <div><span>Request status</span><b>Pending review</b></div>
                    </div>
                    <a class="btn red" href="{{ route('home') }}#reserve">Send another inquiry</a>
                </section>

            @else
                <form class="landing-solari-event-card" id="event-inquiry-form" action="{{ route('events.store') }}" method="POST" novalidate>
                    @csrf
                    <h3 class="h3">2. Contact Information</h3>
                    <div class="two">
                        <label for="first-name">First name *<input id="first-name" name="first_name" autocomplete="given-name" value="{{ old('first_name') }}" maxlength="100" required data-ticket-input="first_name" @if ($errors->has('first_name')) aria-invalid="true" @endif>
                            @error('first_name')<span class="event-error">{{ $message }}</span>@enderror
                        </label>
                        <label for="last-name">Last name *<input id="last-name" name="last_name" autocomplete="family-name" value="{{ old('last_name') }}" maxlength="100" required data-ticket-input="last_name" @if ($errors->has('last_name')) aria-invalid="true" @endif>
                            @error('last_name')<span class="event-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <label for="email">Email address *<input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" maxlength="255" required data-ticket-input="email" @if ($errors->has('email')) aria-invalid="true" @endif>
                        @error('email')<span class="event-error">{{ $message }}</span>@enderror
                    </label>
                    <label for="mobile-number">Mobile number *<input id="mobile-number" name="mobile_number" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('mobile_number') }}" maxlength="30" required data-ticket-input="mobile_number" @if ($errors->has('mobile_number')) aria-invalid="true" @endif>
                        @error('mobile_number')<span class="event-error">{{ $message }}</span>@enderror
                    </label>

                    <h3 class="h3">3. Event Requirements</h3>
                    <div class="landing-event-type-field">
                        <label for="event-type">Event type *
                        <select id="event-type" name="event_type" required data-ticket-input="event_type" @if ($errors->has('event_type')) aria-invalid="true" @endif>
                            <option value="">Select event type</option>
                            @foreach ($eventTypes as $eventType)
                                <option value="{{ $eventType }}" @selected(old('event_type') === $eventType)>{{ $eventType }}</option>
                            @endforeach
                        </select>
                        </label>
                        @error('event_type')<span class="event-error">{{ $message }}</span>@enderror
                        <div class="landing-event-other-type" data-other-event-field @if (old('event_type') !== 'Other') hidden @endif>
                            <label for="event-type-other">Please specify your event type *</label>
                            <input id="event-type-other" name="event_type_other" value="{{ old('event_type_other') }}" maxlength="120" data-ticket-input="event_type_other" @if (old('event_type') === 'Other') required @endif @if ($errors->has('event_type_other')) aria-invalid="true" @endif>
                            @error('event_type_other')<span class="event-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="two">
                        <div class="landing-event-time-range">
                            <label for="preferred-time-from">Preferred time *</label>
                            <div class="landing-event-time-selects">
                                <label for="preferred-time-from">From
                                    <select id="preferred-time-from" name="preferred_time_from" required data-ticket-input="preferred_time_from" @if ($errors->has('preferred_time_from')) aria-invalid="true" @endif>
                                        <option value="">Start time</option>
                                        @foreach ($preferredTimes as $preferredTime)
                                            <option value="{{ $preferredTime }}" @selected(old('preferred_time_from') === $preferredTime)>{{ $preferredTime }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label for="preferred-time-to">To
                                    <select id="preferred-time-to" name="preferred_time_to" required data-ticket-input="preferred_time_to" @if ($errors->has('preferred_time_to')) aria-invalid="true" @endif>
                                        <option value="">End time</option>
                                        @foreach ($preferredTimes as $preferredTime)
                                            <option value="{{ $preferredTime }}" @selected(old('preferred_time_to') === $preferredTime)>{{ $preferredTime }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            @error('preferred_time_from')<span class="event-error">{{ $message }}</span>@enderror
                            @error('preferred_time_to')<span class="event-error">{{ $message }}</span>@enderror
                        </div>
                        <label for="guest-count">Number of guests (pax) *
                            <input id="guest-count" name="guest_count" type="number" min="1" max="30" step="1" inputmode="numeric" value="{{ old('guest_count') }}" required data-ticket-input="guest_count" @if ($errors->has('guest_count')) aria-invalid="true" @endif>
                            @error('guest_count')<span class="event-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <label for="event-package">Package
                        <select id="event-package" name="event_package_id" data-ticket-input="event_package_id">
                            <option value="" data-placeholder>Select guest count first</option>
                            @foreach ($eventPackages as $package)
                                <option value="{{ $package->id }}" data-name="{{ $package->name }}" data-min-guests="{{ $package->min_guests }}" data-max-guests="{{ $package->max_guests }}" @selected((string) old('event_package_id') === (string) $package->id)>{{ $package->name }} ({{ $package->min_guests }}–{{ $package->max_guests }} pax) — ₱{{ number_format((float) $package->fixed_price, 2) }}</option>
                            @endforeach
                        </select>
                        <small id="package-capacity-hint">Enter the guest count to see eligible packages.</small>
                        @error('event_package_id')<span class="event-error">{{ $message }}</span>@enderror
                    </label>
                    <label for="dietary-restrictions">Food preferences / dietary restrictions
                        <textarea id="dietary-restrictions" name="dietary_restrictions" rows="3" maxlength="5000" data-ticket-input="dietary_restrictions">{{ old('dietary_restrictions') }}</textarea>
                        @error('dietary_restrictions')<span class="event-error">{{ $message }}</span>@enderror
                    </label>
                    <label for="special-requests">Special requests / message
                        <textarea id="special-requests" name="special_requests" rows="3" maxlength="5000" data-ticket-input="special_requests">{{ old('special_requests') }}</textarea>
                        @error('special_requests')<span class="event-error">{{ $message }}</span>@enderror
                    </label>
                    <p class="note">Submitting an inquiry does not automatically confirm your event booking.</p>
                    <button class="btn red" type="submit">Submit inquiry</button>
                </form>
            @endif
        </div>

    </div>
</section>

@push('scripts')
    @vite('resources/js/landing-events.js')
@endpush
