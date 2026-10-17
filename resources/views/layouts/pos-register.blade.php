<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>@yield('title', 'Passenger Seat POS')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @include('partials.theme-init')
    @vite(['resources/css/pos-register.css', 'resources/css/app-theme.css', 'resources/js/pos-shell.js', 'resources/js/app-theme.js'])
</head>
<body>
    @php($activePosTab = trim($__env->yieldContent('pos-tab', 'register')))
    <header class="top">
        <div class="brand">Passenger <span>Seat</span> POS</div>
        <nav class="tabs" id="tabs" role="tablist" aria-label="POS screens">
            <button type="button" role="tab" data-url="{{ route('staff.orders.create') }}" aria-selected="{{ $activePosTab === 'register' ? 'true' : 'false' }}">Register</button>
            <button type="button" role="tab" data-url="{{ route('staff.pos.orders') }}" aria-selected="{{ $activePosTab === 'orders' ? 'true' : 'false' }}">Orders <span class="badge b-p">{{ $pendingOrderCount }}</span></button>
            <button type="button" role="tab" data-url="{{ route('staff.pos.analytics') }}" aria-selected="{{ $activePosTab === 'sales' ? 'true' : 'false' }}">Sales</button>
            <button type="button" role="tab" data-url="{{ route('staff.pos.index') }}" aria-selected="{{ $activePosTab === 'billing' ? 'true' : 'false' }}">Billing <span class="badge b-p">{{ $unpaidOrderCount }}</span></button>
            <button type="button" role="tab" data-url="{{ route('staff.pos.table-qr') }}" aria-selected="{{ $activePosTab === 'table-qr' ? 'true' : 'false' }}">Table QR</button>
        </nav>
        <div class="top-actions">
            <time class="clock" id="clock"></time>
            <x-theme-toggle class="btn sm" />
            <form method="POST" action="{{ route('staff.logout') }}">
                @csrf
                <button class="btn sm sign-out" type="submit">Sign out</button>
            </form>
        </div>
    </header>
    <main>
    @yield('content')
    </main>
    <div id="toast" role="status" data-message="{{ session('error') ?: ($errors->first() ?: (session('status') ?: session('success'))) }}"></div>
    @stack('scripts')
</body>
</html>
