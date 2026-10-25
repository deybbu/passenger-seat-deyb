@extends('layouts.admin')
@section('title','Event deposits')
@section('page-title','Event deposits & payment details')
@section('content')
<div class="admin-card" style="margin-bottom:18px"><div class="admin-card-header"><span class="admin-card-title">Proposal payment settings</span><a class="btn-outline" href="{{ route('admin.reference') }}">Back to admin</a></div>
<form method="POST" enctype="multipart/form-data" action="{{ route('admin.event-payment-settings.save') }}" class="admin-form-grid">@csrf
<label class="admin-field">Deposit percentage<input type="number" name="deposit_percent" min="1" max="100" step="0.01" value="{{ old('deposit_percent',$settings['deposit_percent']) }}" required></label>
<label class="admin-field">Date hold duration (hours)<input type="number" name="hold_duration_hours" min="1" max="720" value="{{ old('hold_duration_hours',$settings['hold_duration_hours']) }}" required></label>
<label class="admin-field">Bank / payment provider<input name="bank_name" maxlength="120" value="{{ old('bank_name',$settings['bank_name']) }}"></label>
<label class="admin-field">Account name<input name="account_name" maxlength="120" value="{{ old('account_name',$settings['account_name']) }}"></label>
<label class="admin-field">Account number<input name="account_number" maxlength="80" value="{{ old('account_number',$settings['account_number']) }}"></label>
<label class="admin-field">Payment QR code (PNG)<input type="file" name="qr_image" accept="image/png"></label>
<label class="admin-field">Customer support email<input type="email" name="contact_email" value="{{ old('contact_email',$settings['contact_email']) }}"></label>
<label class="admin-field">Customer support phone<input name="contact_phone" maxlength="40" value="{{ old('contact_phone',$settings['contact_phone']) }}"></label>
<label class="admin-field" style="grid-column:1/-1">Deposit instructions<textarea name="deposit_policy" rows="3">{{ old('deposit_policy',$settings['deposit_policy']) }}</textarea></label>
<label class="admin-field" style="grid-column:1/-1">Cancellation policy<textarea name="cancellation_policy" rows="3">{{ old('cancellation_policy',$settings['cancellation_policy']) }}</textarea></label>
<div><button class="btn-red" type="submit">Save payment details</button></div></form></div>
<div class="admin-card"><div class="admin-card-header"><span class="admin-card-title">Deposit proof awaiting review</span></div>
<div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Customer / event</th><th>Amount / date</th><th>Proof</th><th>Review</th></tr></thead><tbody>
@forelse($deposits as $deposit)
<tr><td>{{ $deposit->proposal->snapshot['customer']['name'] }}<small class="admin-muted admin-block">{{ $deposit->proposal->snapshot['event']['reference'] }} · {{ $deposit->proposal->snapshot['event']['type'] }} · {{ \Illuminate\Support\Carbon::parse($deposit->proposal->snapshot['event']['date'])->format('M j, Y') }}</small></td>
<td>₱{{ number_format((float)$deposit->amount,2) }}<small class="admin-muted admin-block">{{ $deposit->paid_at?->format('M j, Y') ?: 'Date not provided' }}</small></td>
<td><a class="btn-outline" href="{{ route('admin.event-deposits.proof',$deposit) }}">Download proof</a></td><td>
<form method="POST" action="{{ route('admin.event-deposits.review',$deposit) }}" style="min-width:240px">@csrf<input type="hidden" name="decision" value="verified"><label class="admin-field">Review note<input name="review_note" maxlength="2000"></label><label class="admin-field">Expired-hold override reason (if needed)<input name="override_reason" maxlength="2000"></label><button class="btn-red" type="submit">Verify &amp; confirm booking</button></form>
<form method="POST" action="{{ route('admin.event-deposits.review',$deposit) }}" style="margin-top:8px">@csrf<input type="hidden" name="decision" value="rejected"><label class="admin-field">Reason<input name="review_note" maxlength="2000" required></label><button class="btn-outline" type="submit">Reject proof</button></form></td></tr>
@empty<tr><td colspan="4" class="admin-muted">No deposits awaiting review.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
