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
@endsection
