@extends('layouts.admin')

@section('title', 'Waste')
@section('page-title', 'Waste')

@section('content')
    <div class="admin-inventory-grid admin-waste-grid">
        <section class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Log waste</span></div>
            <form method="POST" action="{{ route('admin.waste.store') }}" class="admin-form-grid">
                @csrf
                <label class="admin-field">Item
                    <select name="ingredient_id" required>
                        <option value="">Choose an ingredient</option>
                        @foreach($ingredients as $ingredient)
                            <option value="{{ $ingredient->id }}" @selected(old('ingredient_id') == $ingredient->id)>
                                {{ $ingredient->name }} ({{ $ingredient->unit }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-field">Qty
                    <input type="number" name="quantity" min="0.001" step="0.001" value="{{ old('quantity') }}" required>
                </label>
                <label class="admin-field">Stage
                    <select name="stage" required>
                        <option value="">Choose a stage</option>
                        @foreach($wasteStages as $stage)
                            <option value="{{ $stage }}" @selected(old('stage') === $stage)>{{ $stage }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-field">Reason
                    <select name="reason" required>
                        <option value="">Choose a reason</option>
                        @foreach($wasteReasons as $reason)
                            <option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ $reason }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-field">Other reason details
                    <input type="text" name="other_reason" maxlength="80" value="{{ old('other_reason') }}" placeholder="Required when choosing Others">
                </label>
                <div class="admin-recipe-form-action"><button type="submit" class="btn-red">Log waste</button></div>
            </form>

            <div class="admin-card-header"><span class="admin-card-title">Recent waste</span></div>
            <div class="admin-table-wrap">
                <table class="admin-data-table">
                    <thead><tr><th>Time</th><th>Item</th><th>Qty</th><th>Stage</th><th>Reason</th><th>Cost lost</th></tr></thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('M d, H:i') ?? '—' }}</td>
                                <td class="admin-strong">{{ $log->ingredient->name ?? 'Deleted ingredient' }}</td>
                                <td>{{ number_format(abs((float) $log->quantity), 3) }} {{ $log->ingredient->unit ?? '' }}</td>
                                <td>{{ $log->stage ?: 'Unspecified' }}</td>
                                <td>{{ $log->reason ?: ($log->notes ?: '—') }}</td>
                                <td>{{ $log->cost_amount === null ? '—' : '₱'.number_format((float) $log->cost_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="admin-muted">No waste logged.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="admin-waste-aside">
            <section class="admin-card">
                <div class="admin-card-header"><span class="admin-card-title">Loss by stage</span></div>
                <p class="admin-muted">Y: waste stage · X: cost lost (₱)</p>
                @php
                    $maxStageLoss = $lossByStage->isEmpty() ? 0 : (float) $lossByStage->max();
                @endphp
                @forelse($lossByStage as $stage => $cost)
                    @php
                        $width = $maxStageLoss > 0 ? (($cost / $maxStageLoss) * 100) : 0;
                    @endphp
                    <div class="admin-waste-bar-row">
                        <span>{{ $stage }}</span>
                        <div class="admin-waste-bar-track" role="img" aria-label="{{ $stage }}: ₱{{ number_format($cost, 2) }}">
                            <span style="width: {{ $width }}%"></span>
                        </div>
                        <strong>₱{{ number_format($cost, 2) }}</strong>
                    </div>
                @empty
                    <p class="admin-muted">No data.</p>
                @endforelse
            </section>

            <section class="admin-card">
                <div class="admin-card-header"><span class="admin-card-title">Near expiry</span></div>
                @forelse($nearExpiry as $lot)
                    <div class="admin-near-expiry-row">
                        <span class="admin-inventory-status status-low">{{ $lot->expires_at?->format('Y-m-d') ?? '—' }}</span>
                        <span>{{ $lot->ingredient->name ?? 'Deleted ingredient' }}: {{ number_format((float) $lot->remaining_quantity, 3) }} {{ $lot->ingredient->unit ?? '' }}</span>
                    </div>
                @empty
                    <p class="admin-muted">Nothing close to expiry.</p>
                @endforelse
            </section>
        </div>
    </div>
@endsection
