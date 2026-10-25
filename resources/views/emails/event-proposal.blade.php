<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f5f1ef;color:#292323;font-family:Arial,sans-serif">
<div style="max-width:680px;margin:28px auto;background:#fff;border:1px solid #e6dddd">
<header style="padding:22px 30px;background:#c62828;color:white"><strong style="letter-spacing:2px">PASSENGER SEAT · EVENTS</strong><h1 style="margin:10px 0 0;font-size:25px">Your event proposal</h1></header>
<main style="padding:28px 30px;line-height:1.6">
<p>Hi {{ $snapshot['customer']['name'] }},</p>
<p>Thank you for choosing Passenger Seat. We have placed a temporary hold on your requested date while you review this proposal.</p>
<h2 style="font-size:18px">{{ $snapshot['event']['type'] }} · {{ \Illuminate\Support\Carbon::parse($snapshot['event']['date'])->format('M j, Y') }}</h2>
<p>{{ $snapshot['event']['time'] }} · {{ $snapshot['event']['guests'] }} guests</p>
<p><strong>Package:</strong> {{ $snapshot['package']['name'] }}</p>
@if($snapshot['quote']['packageItems'])<p><strong>Includes:</strong> {{ implode(', ', $snapshot['quote']['packageItems']) }}</p>@endif
<table style="width:100%;border-collapse:collapse">
<tr><td style="padding:7px 0">Package subtotal</td><td style="text-align:right">₱{{ number_format($snapshot['quote']['subtotal'],2) }}</td></tr>
<tr><td style="padding:7px 0">Service charge ({{ $snapshot['quote']['servicePercent'] }}%)</td><td style="text-align:right">₱{{ number_format($snapshot['quote']['service'],2) }}</td></tr>
<tr><td style="padding:7px 0">Tax ({{ $snapshot['quote']['taxPercent'] }}%)</td><td style="text-align:right">₱{{ number_format($snapshot['quote']['tax'],2) }}</td></tr>
<tr><td style="padding:9px 0;border-top:1px solid #ddd"><strong>Estimated total</strong></td><td style="text-align:right;border-top:1px solid #ddd"><strong>₱{{ number_format($snapshot['quote']['total'],2) }}</strong></td></tr>
<tr><td style="padding:7px 0;color:#b32222"><strong>Deposit due ({{ $snapshot['quote']['depositPercent'] }}%)</strong></td><td style="text-align:right;color:#b32222"><strong>₱{{ number_format($snapshot['quote']['deposit'],2) }}</strong></td></tr>
</table>
@if($proposal->personal_note)<p style="white-space:pre-line">{{ $proposal->personal_note }}</p>@endif
<p style="text-align:center;margin:28px 0"><a href="{{ $proposalUrl }}" style="display:inline-block;padding:14px 24px;background:#c62828;color:white;text-decoration:none;font-weight:bold">Review proposal &amp; deposit details</a></p>
<p>The date hold expires {{ $proposal->expires_at->format('M j, Y g:i A') }}. Your booking is confirmed only after the deposit is reviewed and verified by our team.</p>
<p>Use reference <strong>{{ $snapshot['event']['reference'] }}</strong> with your payment.</p>
</main></div></body></html>
