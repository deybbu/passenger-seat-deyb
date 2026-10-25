@php
    $ticketFirstName = old('first_name', data_get(session('event_inquiry'), 'first_name', ''));
    $ticketLastName = old('last_name', data_get(session('event_inquiry'), 'last_name', ''));
    $ticketEventType = old('event_type') === 'Other'
        ? old('event_type_other', '')
        : old('event_type', data_get(session('event_inquiry'), 'event_type', ''));
    $ticketDate = old('event_date', data_get(session('event_inquiry'), 'event_date'));
    $ticketDateLabel = $ticketDate
        ? \Illuminate\Support\Carbon::parse($ticketDate)->translatedFormat('M j, Y')
        : '';
    $ticketTime = old('preferred_time_from') && old('preferred_time_to')
        ? old('preferred_time_from').' to '.old('preferred_time_to')
        : old('preferred_time', data_get(session('event_inquiry'), 'preferred_time', ''));
    $ticketGuests = old('guest_count', data_get(session('event_inquiry'), 'guest_count', ''));
    $ticketPackage = old('preferred_package', data_get(session('event_inquiry'), 'preferred_package', ''));
    $ticketEmail = old('email', data_get(session('event_inquiry'), 'email', ''));
    $ticketMobile = old('mobile_number', data_get(session('event_inquiry'), 'mobile_number', ''));
    $ticketDietary = old('dietary_restrictions', data_get(session('event_inquiry'), 'dietary_restrictions', ''));
    $ticketRequests = old('special_requests', data_get(session('event_inquiry'), 'special_requests', ''));
    $ticketPassenger = trim($ticketFirstName.' '.$ticketLastName);
@endphp

<header class="event-boarding-pass" data-event-ticket>
    <div class="event-boarding-pass-top">
        <div class="event-boarding-brand">
            <span class="event-boarding-mark" aria-hidden="true">✈</span>
            <span><strong>PASSENGER SEAT</strong><small>FLY AND DINE WITH US</small></span>
        </div>
        <div class="event-boarding-class">
            <strong>EVENT BOARDING PASS</strong>
            <span>FIRST CLASS</span>
        </div>
        <div class="event-boarding-stub-brand">
            <strong>PASSENGER SEAT</strong>
            <span>EVENT SERVICES</span>
        </div>
    </div>

    <div class="event-boarding-pass-body">
        <div class="event-boarding-main">
            <div class="event-boarding-eyebrow">EVENTS &amp; PRIVATE DINING</div>
            <div class="event-boarding-destination-label">DESTINATION</div>
            @if ($headingLevel === 'h1')
                <h1>Check Event Availability</h1>
            @else
                <h2>Check Event Availability</h2>
            @endif
            <p class="event-boarding-description">{{ $description }}</p>

            <div class="event-boarding-details">
                <div><span>PASSENGER</span><strong data-ticket-value="passenger">{{ $ticketPassenger ?: 'YOUR NAME' }}</strong></div>
                <div><span>EVENT</span><strong data-ticket-value="event">{{ $ticketEventType ?: 'SELECT EVENT' }}</strong></div>
                <div><span>DATE</span><strong data-ticket-value="date">{{ $ticketDateLabel ?: 'SELECT DATE' }}</strong></div>
                <div><span>TIME</span><strong data-ticket-value="time">{{ $ticketTime ?: 'SELECT TIME' }}</strong></div>
                <div><span>GUESTS</span><strong data-ticket-value="guests">{{ $ticketGuests ? $ticketGuests.' PAX' : 'NOT SET' }}</strong></div>
                <div><span>PACKAGE / BUDGET</span><strong data-ticket-value="package">{{ $ticketPackage ?: 'NOT SET' }}</strong></div>
            </div>
            <div class="event-boarding-gate">
                <span class="event-boarding-dot" aria-hidden="true"></span>
                <span>CONTACT <strong data-ticket-value="contact">{{ trim($ticketEmail.' / '.$ticketMobile, ' /') ?: 'EMAIL / MOBILE' }}</strong></span>
            </div>
            <div class="event-boarding-notes">
                <span>FOOD / DIETARY</span>
                <strong data-ticket-value="dietary">{{ $ticketDietary ?: 'NONE PROVIDED' }}</strong>
                <span>SPECIAL REQUESTS</span>
                <strong data-ticket-value="requests">{{ $ticketRequests ?: 'NONE PROVIDED' }}</strong>
            </div>
        </div>

        <div class="event-boarding-stub" aria-hidden="true">
            <span class="event-boarding-destination-label">YOUR DESTINATION</span>
            <strong class="event-boarding-stub-destination" data-ticket-value="event-stub">{{ $ticketEventType ?: 'YOUR EVENT' }}</strong>
            <span class="event-boarding-barcode"></span>
            <div class="event-boarding-stub-details">
                <span>PASSENGER<strong data-ticket-value="passenger-stub">{{ $ticketPassenger ?: 'YOUR NAME' }}</strong></span>
                <span>DATE<strong data-ticket-value="date-stub">{{ $ticketDateLabel ?: 'SELECT DATE' }}</strong></span>
                <span>TIME<strong data-ticket-value="time-stub">{{ $ticketTime ?: 'SELECT TIME' }}</strong></span>
                <span>GUESTS<strong data-ticket-value="guests-stub">{{ $ticketGuests ? $ticketGuests.' PAX' : 'NOT SET' }}</strong></span>
                <span>EMAIL<strong data-ticket-value="email">{{ $ticketEmail ?: 'NOT PROVIDED' }}</strong></span>
                <span>MOBILE<strong data-ticket-value="mobile">{{ $ticketMobile ?: 'NOT PROVIDED' }}</strong></span>
            </div>
        </div>
    </div>

    <div class="event-boarding-pass-footer">
        <span>YOUR CELEBRATION STARTS HERE</span>
        <span>PS&nbsp; 58AE586210</span>
    </div>
</header>
