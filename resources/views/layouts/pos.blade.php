<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => trim($__env->yieldContent('title', 'POS'))])
    <meta name="robots" content="noindex,nofollow">
    @vite(['resources/css/pos.css', 'resources/js/pos.js'])
</head>
<body class="pos-page">
    @php($user = auth()->user())
    <div class="pos-app">
        <header class="pos-header">
            <a class="pos-brand" href="{{ route('staff.pos.index') }}">Passenger <span>Seat</span> POS</a>
            <nav class="pos-tabs" aria-label="Cashier tools">
                <a href="{{ route('staff.orders.create') }}" @class(['is-active' => request()->routeIs('staff.orders.create')])>Register</a>
                <a href="{{ route('staff.orders.index') }}" @class(['is-active' => request()->routeIs('staff.orders.index')])>Orders</a>
                <a href="{{ route('staff.pos.index') }}" @class(['is-active' => request()->routeIs('staff.pos.index')])>Billing</a>
                <a href="{{ route('staff.pos.ledger') }}" @class(['is-active' => request()->routeIs('staff.pos.ledger') || request()->routeIs('staff.pos.receipt')])>Transactions</a>
                <a href="{{ route('staff.pos.analytics') }}" @class(['is-active' => request()->routeIs('staff.pos.analytics')])>Analytics</a>
                <a href="{{ route('staff.tables.index') }}" @class(['is-active' => request()->routeIs('staff.tables.*')])>Table QR</a>
            </nav>
            <div class="pos-header-actions">
                <time data-pos-clock></time>
                <button type="button" class="pos-theme-toggle" data-pos-theme aria-label="Switch to dark mode">Dark mode</button>
                <form method="POST" action="{{ route('staff.logout') }}">
                    @csrf
                    <button type="submit" class="pos-sign-out">Sign out</button>
                </form>
            </div>
        </header>

        <main class="pos-main">
            <div class="pos-flash"><x-flash /></div>
            @yield('content')
        </main>
    </div>
</body>
</html>
