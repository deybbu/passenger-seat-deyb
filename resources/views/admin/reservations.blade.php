@extends('layouts.admin')

@section('title', 'Reservations & events')
@section('page-title', 'Reservations & events')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header"><span class="admin-card-title">New event inquiry</span></div>
        <form method="POST" action="{{ route('admin.reservations.store') }}" class="admin-form-grid">
            @csrf
            <label class="admin-field">Event date<input type="date" name="event_date" min="{{ today()->toDateString() }}" required></label>
            <label class="admin-field">First name<input name="first_name" required maxlength="100"></label>
            <label class="admin-field">Last name<input name="last_name" required maxlength="100"></label>
            <label class="admin-field">Email<input type="email" name="email" required maxlength="255"></label>
            <label class="admin-field">Mobile number<input name="mobile_number" required maxlength="30"></label>
            <label class="admin-field">Event type<select name="event_type" required>@foreach(\App\Models\EventInquiry::EVENT_TYPES as $type)<option>{{ $type }}</option>@endforeach</select></label>
            <label class="admin-field">Preferred time<select name="preferred_time" required>@foreach(\App\Models\EventInquiry::PREFERRED_TIMES as $time)<option>{{ $time }}</option>@endforeach</select></label>
            <label class="admin-field">Guests<input type="number" name="guest_count" min="1" required></label>
            <label class="admin-field">Budget<input type="number" name="budget" min="0" step="0.01"></label>
            <label class="admin-field">Preferred package<select name="preferred_package"><option value="">Not sure yet</option>@foreach(\App\Models\EventInquiry::PACKAGE_OPTIONS as $package)<option>{{ $package }}</option>@endforeach</select></label>
            <label class="admin-field" style="grid-column:1/-1;">Special requests<textarea name="special_requests" rows="2" maxlength="5000"></textarea></label>
            <div><button class="btn-red" type="submit">Add inquiry</button></div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Inquiries
                @foreach(['Inquiries', 'Booked', 'Archived'] as $option)
                    <a class="btn-outline {{ $tab === $option ? 'is-active' : '' }}" href="{{ route('admin.reservations', ['tab' => $option]) }}">{{ $option }}</a>
                @endforeach
            </span>
        </div>
        <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Received</th><th>Event date</th><th>Customer</th><th>Event</th><th>Budget</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($inquiries as $inquiry)
                <tr>
                    <td>{{ $inquiry->created_at?->format('M j, Y') }}</td>
                    <td>{{ $inquiry->event_date->format('M j, Y') }}</td>
                    <td class="admin-strong">{{ $inquiry->first_name }} {{ $inquiry->last_name }}<small class="admin-muted admin-block">{{ $inquiry->email }} · {{ $inquiry->mobile_number }}</small></td>
                    <td>{{ $inquiry->event_type }} ({{ $inquiry->guest_count }} pax)</td>
                    <td>{{ $inquiry->budget === null ? '—' : '₱'.number_format((float) $inquiry->budget, 2) }}</td>
                    <td><span class="badge-status badge-{{ $inquiry->status === 'approved' ? 'in-stock' : ($inquiry->status === 'declined' ? 'out-of-stock' : 'low-stock') }}">{{ ucfirst(str_replace('_', ' ', $inquiry->status)) }}</span></td>
                    <td>
                        <details>
                            <summary class="btn-outline">Details</summary>
                            <div style="min-width:280px;padding:10px;">
                                <p>{{ $inquiry->special_requests ?: 'No special requests.' }}</p>
                                <form method="POST" action="{{ route('admin.reservations.update', $inquiry) }}">
                                    @csrf @method('PATCH')
                                    <label class="admin-field">Decision<select name="status"><option value="pending" @selected($inquiry->status === 'pending')>Under review</option><option value="approved" @selected($inquiry->status === 'approved')>Approved</option><option value="declined" @selected($inquiry->status === 'declined')>Declined</option></select></label>
                                    <label class="admin-field">Package<select name="event_package_id"><option value="">No package</option>@foreach($packages as $package)<option value="{{ $package->id }}" @selected($inquiry->event_package_id === $package->id)>{{ $package->name }}</option>@endforeach</select></label>
                                    <button class="btn-red" type="submit">Save decision</button>
                                </form>
                                <form method="POST" action="{{ route('admin.reservations.notes.store', $inquiry) }}" style="margin-top:8px;">
                                    @csrf
                                    <label class="admin-field">Internal note<textarea name="note" rows="2" required></textarea></label>
                                    <button class="btn-outline" type="submit">Add note</button>
                                </form>
                                @foreach($inquiry->internalNotes as $note)<p class="admin-muted">• {{ $note->note }}</p>@endforeach
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="admin-muted">No inquiries in this tab.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>

    <div class="admin-card" style="margin-top:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Admin calendar blackout dates</span></div>
        <div class="admin-inline-actions">
            @forelse($blackouts as $blackout)
                <form method="POST" action="{{ route('admin.reservations.blackout') }}">
                    @csrf
                    <input type="hidden" name="date" value="{{ $blackout->date->format('Y-m-d') }}">
                    <button class="btn-outline" type="submit">{{ $blackout->date->format('M j, Y') }} · Unblock</button>
                </form>
            @empty
                <span class="admin-muted">No blackout dates.</span>
            @endforelse
        </div>
        <form method="POST" action="{{ route('admin.reservations.blackout') }}" class="admin-inline-actions" style="margin-top:12px;">
            @csrf
            <input type="date" name="date" min="{{ today()->toDateString() }}" required>
            <button class="btn-outline" type="submit">Block date</button>
        </form>
    </div>
@endsection
