@extends('layouts.admin')

@section('title', 'Notifications & alerts')
@section('page-title', 'Notifications & alerts')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Alerts <span class="admin-tab-count">{{ $unreadCount }} unread</span></span>
            <button type="button" class="admin-notify-markall" data-mark-all-read data-url="{{ route('admin.notifications.read-all') }}">Mark all read</button>
        </div>
        <div class="admin-notify-list admin-notifications-page">
            @forelse($alerts as $alert)
                <div class="admin-notify-item {{ $alert['unread'] ? 'unread' : '' }}" data-key="{{ $alert['key'] }}">
                    <div class="admin-notify-item-top">
                        <span class="admin-notify-type">{{ $alert['title'] }}</span>
                        <span class="admin-notify-time">{{ $alert['time'] }}</span>
                    </div>
                    <p>{{ $alert['body'] }}</p>
                    <div class="admin-notify-item-actions">
                        <a href="{{ $alert['href'] }}">Open</a>
                        @if($alert['unread'])
                            <button type="button" class="admin-notify-read" data-key="{{ $alert['key'] }}" data-url="{{ route('admin.notifications.read') }}">Mark read</button>
                        @else
                            <span class="admin-muted">Read</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="admin-notify-empty">No alerts right now.</p>
            @endforelse
        </div>
    </div>
    <div class="admin-card" style="margin-top:16px;">
        <div class="admin-card-header"><span class="admin-card-title">Alert rules</span></div>
        <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Trigger</th><th>Action</th><th>On</th></tr></thead><tbody>
            @forelse($rules as $rule)
                <tr><td>{{ $rule->trigger }}</td><td>{{ $rule->action }}</td><td><form method="POST" action="{{ route('admin.notifications.rules.toggle', $rule) }}">@csrf @method('PATCH')<button class="btn-outline" type="submit">{{ $rule->is_enabled ? 'On' : 'Off' }}</button></form></td></tr>
            @empty
                <tr><td colspan="3" class="admin-muted">No alert rules configured.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
@endsection
