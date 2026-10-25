@extends('layouts.admin')

@section('title', 'Table QR control · Passenger Seat')
@section('page-title', 'Table QR control')

@section('content')
    <div class="admin-operation-page admin-table-qr">
        <header class="admin-operation-hero">
            <div>
                <h1>Table QR control</h1>
                <p>Enable a table QR when guests are seated; disable it when they leave. Disabled QR codes cannot place orders.</p>
            </div>
            <span class="admin-operation-count">{{ $activeCount }} active</span>
        </header>

        @unless ($qrEnabled)
            <div class="admin-operation-message warning" role="status">
                Customer QR menus are globally disabled in System Settings.
            </div>
        @endunless

        @if ($tables->isEmpty())
            <section class="admin-operation-card admin-operation-empty">
                <strong>No tables configured.</strong>
                <span>Table QR controls will appear after a table is opened for service.</span>
            </section>
        @else
            <div class="admin-qr-grid">
                @foreach ($tables as $table)
                    @php($enabled = $table->is_enabled && $qrEnabled)
                    <article class="admin-operation-card admin-qr-card">
                        <div class="admin-operation-card-heading">
                            <h2>{{ $table->name }}</h2>
                            <span class="admin-operation-badge {{ $enabled ? 'enabled' : 'disabled' }}">
                                {{ $enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        <p>{{ $enabled ? 'QR ordering available' : 'QR ordering blocked' }}</p>
                        <a class="admin-qr-code" href="{{ route('customer.qr.show', $table->qr_token
                            ? ['qr' => $table->qr_token]
                            : ['table' => $table->table_number]) }}"
                           aria-label="Open QR ordering page for {{ $table->name }}">
                            <svg viewBox="0 0 64 64" role="img" aria-label="QR code placeholder">
                                <rect class="qr-background" width="64" height="64" rx="4"/>
                                <g class="qr-foreground">
                                    <path d="M5 5h20v20H5zM9 9v12h12V9zM39 5h20v20H39zM43 9v12h12V9zM5 39h20v20H5zM9 43v12h12V43zM31 5h4v4h-4zM31 13h4v8h-4zM31 25h8v4h-8zM43 31h4v8h-4zM51 31h8v4h-8zM31 35h8v4h-8zM35 43h4v8h-4zM43 47h4v12h-4zM51 39h8v4h-8zM55 51h4v8h-4zM27 51h8v4h-8z"/>
                                </g>
                            </svg>
                            <span>QR for {{ $table->name }}</span>
                        </a>
                        <form method="POST" action="{{ route('admin.table-qr.toggle', $table) }}">
                            @csrf
                            @method('PATCH')
                            <button class="admin-operation-button {{ $table->is_enabled ? 'secondary' : 'primary' }}"
                                    type="submit" @disabled(! $qrEnabled)>
                                {{ $table->is_enabled ? 'Disable QR' : 'Enable QR' }}
                            </button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
