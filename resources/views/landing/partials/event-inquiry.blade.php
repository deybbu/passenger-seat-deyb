<section id="reserve">
    <div class="wrap">
        <div class="ey">Events &amp; private dining</div>
        <h2>Check event availability</h2>
        <p style="color:var(--mute);max-width:520px">Birthdays, christenings, corporate events and whole-restaurant rental. Pick an available date, then tell us about your event.</p>

        <div class="evg">
            <div class="card">
                <h3 class="h3">1. Event availability calendar</h3>
                <div class="legend" aria-label="Legend">
                    <span><i class="dot a" aria-hidden="true"></i>Available</span>
                    <span><i class="dot p" aria-hidden="true"></i>Pending</span>
                    <span><i class="dot b" aria-hidden="true"></i>Booked</span>
                </div>

                <div
                    class="landing-event-calendar"
                    data-event-calendar
                    data-today="{{ $today }}"
                    data-availability='@js($availability)'
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
                    <input id="event-date" type="hidden" name="event_date" form="event-inquiry-form" value="{{ old('event_date') }}">
                    <p class="err" id="event-date-error" role="alert">{{ $errors->first('event_date') }}</p>
                </div>
                <noscript><p class="event-error">JavaScript is required to choose an available date on the calendar.</p></noscript>
            </div>

            @if ($confirmation)
                <section class="card event-confirmation" role="status" aria-live="polite">
                    <span class="event-confirmation-icon" aria-hidden="true">✓</span>
                    <h3 class="h3">Inquiry received</h3>
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
                <form id="event-inquiry-form" action="{{ route('events.store') }}" method="POST" novalidate>
                    @csrf
                    <h3 class="h3">2. Contact information</h3>
                    <div class="two">
                        <label for="first-name">First name *<input id="first-name" name="first_name" autocomplete="given-name" value="{{ old('first_name') }}" maxlength="100" required @if ($errors->has('first_name')) aria-invalid="true" @endif>
                            @error('first_name')<span class="event-error">{{ $message }}</span>@enderror
                        </label>
                        <label for="last-name">Last name *<input id="last-name" name="last_name" autocomplete="family-name" value="{{ old('last_name') }}" maxlength="100" required @if ($errors->has('last_name')) aria-invalid="true" @endif>
                            @error('last_name')<span class="event-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <label for="email">Email address *<input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" maxlength="255" required @if ($errors->has('email')) aria-invalid="true" @endif>
                        @error('email')<span class="event-error">{{ $message }}</span>@enderror
                    </label>
                    <label for="mobile-number">Mobile number *<input id="mobile-number" name="mobile_number" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('mobile_number') }}" maxlength="30" required @if ($errors->has('mobile_number')) aria-invalid="true" @endif>
                        @error('mobile_number')<span class="event-error">{{ $message }}</span>@enderror
                    </label>

                    <h3 class="h3">3. Event requirements</h3>
                    <label for="event-type">Event type *
                        <select id="event-type" name="event_type" required @if ($errors->has('event_type')) aria-invalid="true" @endif>
                            <option value="">Select event type</option>
                            @foreach ($eventTypes as $eventType)
                                <option value="{{ $eventType }}" @selected(old('event_type') === $eventType)>{{ $eventType }}</option>
                            @endforeach
                        </select>
                        @error('event_type')<span class="event-error">{{ $message }}</span>@enderror
                    </label>
                    <div class="two">
                        <label for="preferred-time">Preferred time *
                            <select id="preferred-time" name="preferred_time" required @if ($errors->has('preferred_time')) aria-invalid="true" @endif>
                                <option value="">Select preferred time</option>
                                @foreach ($preferredTimes as $preferredTime)
                                    <option value="{{ $preferredTime }}" @selected(old('preferred_time') === $preferredTime)>{{ $preferredTime }}</option>
                                @endforeach
                            </select>
                            @error('preferred_time')<span class="event-error">{{ $message }}</span>@enderror
                        </label>
                        <label for="guest-count">Number of guests (pax) *
                            <input id="guest-count" name="guest_count" type="number" min="1" step="1" inputmode="numeric" value="{{ old('guest_count') }}" required @if ($errors->has('guest_count')) aria-invalid="true" @endif>
                            @error('guest_count')<span class="event-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <label for="preferred-package">Preferred package / budget
                        <select id="preferred-package" name="preferred_package">
                            <option value="">Not sure yet</option>
                            @foreach ($packageOptions as $packageOption)
                                <option value="{{ $packageOption }}" @selected(old('preferred_package') === $packageOption)>{{ $packageOption }}</option>
                            @endforeach
                        </select>
                        @error('preferred_package')<span class="event-error">{{ $message }}</span>@enderror
                    </label>
                    <label for="dietary-restrictions">Food preferences / dietary restrictions
                        <textarea id="dietary-restrictions" name="dietary_restrictions" rows="3" maxlength="5000">{{ old('dietary_restrictions') }}</textarea>
                        @error('dietary_restrictions')<span class="event-error">{{ $message }}</span>@enderror
                    </label>
                    <label for="special-requests">Special requests / message
                        <textarea id="special-requests" name="special_requests" rows="3" maxlength="5000">{{ old('special_requests') }}</textarea>
                        @error('special_requests')<span class="event-error">{{ $message }}</span>@enderror
                    </label>
                    <p class="note">Submitting an inquiry does not automatically confirm your event booking.</p>
                    <button class="btn red" type="submit">Submit inquiry</button>
                </form>
            @endif
        </div>
    </div>
</section>
