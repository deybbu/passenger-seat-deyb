@extends('layouts.admin')

@section('title', 'Slow-Moving Items')
@section('page-title', 'Slow-Moving Items')

@section('content')
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Slow-Moving Items</span><span class="admin-muted">Analytics &amp; reports · rolling 120-day view</span></div>
        <p class="admin-muted">Fast-Moving: 40+ units sold. Very Slow: 1–20 units. Slow: 21–39 units. Counts reflect available system order history.</p>
    </div>
    <div class="admin-stats-row">
        @foreach([['No Sales', $noSales], ['Very Slow', $verySlow], ['Slow', $slow], ['Fast-Moving', $fast], ['Ingredient Wastage Value at Risk', '₱'.number_format($wasteAtRisk, 2)]] as $stat)
            <div class="admin-stat-card"><span class="admin-stat-title">{{ $stat[0] }}</span><strong class="admin-stat-number">{{ $stat[1] }}</strong></div>
        @endforeach
    </div>
    <div class="admin-card" style="margin-top:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Menu item performance</span></div>
        <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Menu item</th><th>Units sold</th><th>Sales status</th><th>Recommendation</th><th>Kitchen notes</th></tr></thead><tbody>
            @forelse($rows as $row)
                @php($status = $row['units'] === 0 ? 'No Sales' : ($row['units'] <= 20 ? 'Very Slow' : ($row['units'] <= 39 ? 'Slow' : 'Fast-Moving')))
                <tr><td class="admin-strong">{{ $row['item']->name }}</td><td>{{ $row['units'] }}</td><td><span class="badge-status badge-{{ $row['units'] === 0 ? 'out-of-stock' : ($row['units'] <= 20 ? 'low-stock' : 'in-stock') }}">{{ $status }}</span></td><td>{{ $row['units'] === 0 ? 'Review recipe, visibility and demand' : ($row['units'] < 40 ? 'Review prep stock and menu placement' : 'Maintain kitchen prep stock') }}</td><td>{{ $row['item']->description ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="5" class="admin-muted">No menu items are available.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
@endsection
