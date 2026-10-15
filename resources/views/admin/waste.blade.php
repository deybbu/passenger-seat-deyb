@extends('layouts.admin')

@section('title', 'Food waste monitoring')
@section('page-title', 'Food waste monitoring')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Log food waste</span></div>
        <form method="POST" action="{{ route('admin.waste.store') }}" class="admin-form-grid">
            @csrf
            <label class="admin-field">Ingredient
                <select name="ingredient_id" required>
                    <option value="">Choose an ingredient</option>
                    @foreach($ingredients as $ingredient)
                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->unit }})</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">Quantity wasted
                <input type="number" name="quantity" min="0.001" step="0.001" required>
            </label>
            <label class="admin-field">Reason
                <select name="reason" required>
                    <option value="">Choose a reason</option>
                    <option>Pre-Cook Spoilage</option>
                    <option>Over-Prep Waste</option>
                    <option>Boodle Return</option>
                    <option>Other</option>
                </select>
            </label>
            <label class="admin-field">Reason details
                <input type="text" name="notes" maxlength="500" required placeholder="Add details for the loss record">
            </label>
            <div class="admin-recipe-form-action"><button type="submit" class="btn-red">Record waste</button></div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Recent waste records</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead><tr><th>Ingredient</th><th>Quantity</th><th>Recorded by</th><th>Reason and details</th><th>Estimated cost</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="admin-strong">{{ $log->ingredient->name ?? 'Deleted ingredient' }}</td>
                            <td>{{ number_format(abs((float) $log->quantity), 3) }} {{ $log->ingredient->unit ?? '' }}</td>
                            <td>{{ $log->user->name ?? 'System' }}</td>
                            <td>{{ $log->reason ?? 'Reason not recorded' }}@if ($log->notes) · {{ $log->notes }}@endif</td>
                            <td>{{ $log->cost_amount === null ? 'Not available' : '₱'.number_format((float) $log->cost_amount, 2) }}</td>
                            <td>{{ $log->created_at->format('M j, Y g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="admin-muted">No waste has been recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
