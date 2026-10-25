<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f5f1ef;color:#292323;font-family:Arial,sans-serif">
<div style="max-width:620px;margin:28px auto;background:#fff;border:1px solid #e6dddd">
<header style="padding:22px 30px;background:#c62828;color:white"><strong style="letter-spacing:2px">PASSENGER SEAT · EVENTS</strong><h1 style="margin:10px 0 0;font-size:24px">Your booking is confirmed</h1></header>
<main style="padding:28px 30px;line-height:1.65"><p>Hi {{ $inquiry->first_name }},</p>
<p>We reviewed your deposit and confirmed your event booking. We look forward to celebrating with you.</p>
<p><strong>Reference:</strong> {{ $inquiry->reference }}<br><strong>Event:</strong> {{ $inquiry->event_type }}<br><strong>Date:</strong> {{ $inquiry->event_date->format('M j, Y') }}<br><strong>Time:</strong> {{ $inquiry->preferred_time }}<br><strong>Package:</strong> {{ $proposal->snapshot['package']['name'] }}<br><strong>Guests:</strong> {{ $inquiry->guest_count }}</p>
<p>Please quote your reference if you contact the restaurant.</p><p>Passenger Seat Events</p></main>
</div></body></html>
