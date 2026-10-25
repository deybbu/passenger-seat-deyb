@extends('layouts.public-landing')
@section('title','Event proposal · Passenger Seat')
@section('content')
<section class="landing-events-page deposit-ticket-page"><div class="landing-events-inner">
<article class="event-boarding-pass deposit-boarding-pass">
<header class="event-boarding-pass-top"><div class="event-boarding-brand"><span aria-hidden="true">✈</span><span><strong>PASSENGER SEAT</strong><small>FLY AND DINE WITH US</small></span></div><div class="event-boarding-class"><strong>EVENT PROPOSAL</strong><span>PRIVATE LINK</span></div><div class="event-boarding-stub-brand"><strong>EVENT SERVICES</strong><span>PROPOSAL {{ $proposal->version }}</span></div></header>
<div class="event-boarding-pass-body"><main class="event-boarding-main">
<div class="event-boarding-eyebrow">EVENTS &amp; PRIVATE DINING</div><div class="event-boarding-destination-label">PROPOSAL DETAILS</div><h1>REVIEW YOUR PROPOSAL</h1>
<p class="event-boarding-description">Hi {{ $inquiry->first_name }}, review your event details, estimated cost and deposit instructions below.</p>
@if(session('success'))<div role="status" style="padding:12px;background:#edf8ed;border:1px solid #9bc99b;margin:12px 0">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" style="padding:12px;background:#fff0f0;border:1px solid #e5aaaa;margin:12px 0">{{ $errors->first() }}</div>@endif
<div class="event-boarding-details deposit-ticket-details"><div><span>REFERENCE</span><strong>{{ $inquiry->reference }}</strong></div><div><span>CUSTOMER</span><strong>{{ $inquiry->first_name }} {{ $inquiry->last_name }}</strong></div><div><span>EVENT</span><strong>{{ $inquiry->event_type }}</strong></div><div><span>DATE</span><strong>{{ $inquiry->event_date->format('M j, Y') }}</strong></div><div><span>PACKAGE</span><strong>{{ $proposal->snapshot['package']['name'] }}</strong></div><div><span>GUESTS</span><strong>{{ $inquiry->guest_count }} PAX</strong></div></div>
@if($quote['packageItems'])<p><strong>Package includes:</strong> {{ implode(', ', $quote['packageItems']) }}</p>@endif
<div class="event-boarding-notes deposit-pricing"><span>PACKAGE SUBTOTAL</span><strong>₱{{ number_format($quote['subtotal'],2) }}</strong><span>SERVICE CHARGE ({{ $quote['servicePercent'] }}%)</span><strong>₱{{ number_format($quote['service'],2) }}</strong><span>TAX ({{ $quote['taxPercent'] }}%)</span><strong>₱{{ number_format($quote['tax'],2) }}</strong><span>ESTIMATED TOTAL</span><strong>₱{{ number_format($quote['total'],2) }}</strong><span>{{ $quote['depositPercent'] }}% RESERVATION DEPOSIT</span><strong class="deposit-due">₱{{ number_format($quote['deposit'],2) }}</strong></div>
@if($proposal->personal_note)<p style="white-space:pre-line">{{ $proposal->personal_note }}</p>@endif
</main><aside class="event-boarding-stub"><span class="event-boarding-destination-label">PAYMENT ARRANGEMENTS</span>
<p>Deposit due <strong>₱{{ number_format($quote['deposit'],2) }}</strong></p>
@if($qrImage)<img src="{{ $qrImage }}" alt="Payment QR code" style="max-width:220px;width:100%;height:auto">@endif
@if($payment['bank_name'])<p><strong>{{ $payment['bank_name'] }}</strong><br>Account name: {{ $payment['account_name'] }}<br>Account number: {{ $payment['account_number'] }}</p>@endif
@if($payment['deposit_policy'])<p>{{ $payment['deposit_policy'] }}</p>@endif
@if($payment['cancellation_policy'])<p><strong>Cancellation policy</strong><br>{{ $payment['cancellation_policy'] }}</p>@endif
@if($payment['contact_email'])<p>Questions? <a href="mailto:{{ $payment['contact_email'] }}">{{ $payment['contact_email'] }}</a></p>@endif
<p><strong>Date hold ends:</strong><br>{{ $proposal->expires_at->format('M j, Y g:i A') }}</p>
</aside></div>
<div style="padding:18px 28px;border-top:1px dashed #ccc">
@if(!$proposal->accepted_at)
<form method="POST" action="{{ route('events.proposal.accept',$proposal->token_encrypted) }}">@csrf<label style="display:block;margin-bottom:12px"><input type="checkbox" name="accept_terms" value="1" required> I accept this proposal. I understand the booking is provisional until the restaurant verifies my deposit.</label><button class="btn-red" type="submit">Accept proposal</button></form>
@elseif(!$depositRecord || $depositRecord->status === 'rejected')
<h2>Submit deposit proof</h2><p>Pay using the instructions above, then upload your receipt or transfer screenshot. Include reference {{ $inquiry->reference }}.</p>
<form method="POST" enctype="multipart/form-data" action="{{ route('events.proposal.deposit',$proposal->token_encrypted) }}">@csrf<div class="admin-form-grid"><label>Amount paid<input type="number" name="amount" min="0.01" step="0.01" required></label><label>Payment date<input type="date" name="paid_at" max="{{ today()->toDateString() }}"></label><label>Payment method<input name="method" maxlength="40" placeholder="Bank transfer, GCash, etc."></label><label>Receipt / proof<input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required></label></div><button class="btn-red" type="submit">Submit deposit proof</button></form>
@else
<h2>Deposit {{ ucfirst($depositRecord->status) }}</h2><p>Amount submitted: ₱{{ number_format((float)$depositRecord->amount,2) }}. The restaurant will email you after reviewing the payment.</p>
@endif
</div><footer style="padding:14px 28px;background:#c62828;color:white;font-size:12px">Your celebration starts here · Proposal expires {{ $proposal->expires_at->format('M j, Y g:i A') }}</footer>
</article></div></section>
@endsection
