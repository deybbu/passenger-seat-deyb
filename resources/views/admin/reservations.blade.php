@extends('layouts.admin')

@section('title', 'Reservations & events')
@section('page-title', 'Reservations & events')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Add reservation</span></div>
        <form method="POST" action="{{ route('admin.reservations.store') }}" class="admin-form-grid">
            @csrf
            <label class="admin-field">Guest name
                <input type="text" name="guest_name" maxlength="120" required>
            </label>
            <label class="admin-field">Phone
                <input type="tel" name="guest_phone" maxlength="30">
            </label>
            <label class="admin-field">Party size
                <input type="number" name="party_size" min="1" max="40" required>
            </label>
            <label class="admin-field">Date & time
                <input type="datetime-local" name="reserved_at" required>
            </label>
            <label class="admin-field">Notes
                <input type="text" name="notes" maxlength="500">
            </label>
            <div><button type="submit" class="btn-red">Add reservation</button></div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Reservations</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Guest</th><th>Party</th><th>Reserved for</th><th>Phone</th><th>Status</th><th>Update</th></tr></thead>
                <tbody>
                    @forelse($reservations as $reservation)
                        <tr>
                            <td class="admin-strong">{{ $reservation->guest_name }}<br><span class="admin-muted">{{ $reservation->notes }}</span></td>
                            <td>{{ $reservation->party_size }}</td>
                            <td>{{ $reservation->reserved_at->format('M j, Y g:i A') }}</td>
                            <td>{{ $reservation->guest_phone ?: '—' }}</td>
                            <td>{{ ucfirst($reservation->status) }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.reservations.update', $reservation) }}" class="admin-inline-form">
                                    @csrf
                                    @method('PATCH')
                                    <label class="admin-field">
                                        <span class="sr-only">Reservation status</span>
                                        <select name="status" aria-label="Status for {{ $reservation->guest_name }}">
                                            @foreach(['upcoming', 'seated', 'completed', 'cancelled'] as $status)
                                                <option value="{{ $status }}" @selected($reservation->status === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button type="submit" class="btn-outline">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="admin-muted">No reservations have been added yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
