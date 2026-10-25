<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f5f1ef;color:#292323;font-family:Arial,sans-serif">
<div style="max-width:620px;margin:28px auto;background:#fff;border:1px solid #e6dddd">
<header style="padding:22px 30px;background:#c62828;color:white"><strong style="letter-spacing:2px">PASSENGER SEAT · EVENTS</strong><h1 style="margin:10px 0 0;font-size:24px">We received your inquiry</h1></header>
<main style="padding:28px 30px;line-height:1.65"><p>Hi {{ $inquiry->first_name }},</p>
<p>Thanks for reaching out to Passenger Seat. Our team received your {{ $inquiry->event_type }} inquiry and will review the details. We’ll email you a proposal with the package cost and deposit instructions after confirming your request.</p>
<p><strong>Inquiry reference:</strong> {{ $inquiry->reference }}<br><strong>Requested date:</strong> {{ $inquiry->event_date->format('M j, Y') }}<br><strong>Guests:</strong> {{ $inquiry->guest_count }}</p>
<p>This is an inquiry confirmation; your date is not yet booked or held. Please quote the reference if you contact our team.</p><p>Passenger Seat Events</p></main>
</div></body></html>
