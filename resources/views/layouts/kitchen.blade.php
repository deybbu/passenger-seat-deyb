<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('partials.favicon')
    <title>{{ trim($__env->yieldContent('title', 'Kitchen')) }} | Passenger Seat Restaurant</title>
    <meta name="robots" content="noindex,nofollow">
    @include('partials.theme-init')
    @vite(['resources/css/kitchen.css', 'resources/css/app-theme.css', 'resources/js/kitchen.js', 'resources/js/app-theme.js'])
</head>
<body>
    @php($user = auth()->user())
    <div data-kitchen-app
         data-board-url="{{ route('staff.kitchen.board') }}"
         data-inventory-url="{{ route('staff.kitchen.inventory') }}">
        <header>
            <div class="lg" aria-hidden="true">
                <svg class="i" viewBox="0 0 24 24"><path d="M6 13.9V20h12v-6.1M4 11a4 4 0 0 1 4-4 4 4 0 0 1 8 0 4 4 0 0 1 4 4z"/></svg>
            </div>
            <div class="ht"><b>Kitchen</b><small>Passenger Seat</small></div>
            <nav class="tabs" role="tablist" aria-label="Kitchen sections">
                <button type="button" class="on" data-v="orders" role="tab" aria-controls="v-orders" aria-selected="true">
                    Orders <span class="cnt" data-pending-count>{{ $pending->count() }}</span>
                </button>
                <button type="button" data-v="inv" role="tab" aria-controls="v-inv" aria-selected="false">
                    Inventory <span class="cnt" data-low-stock-count>{{ $lowStockCount }}</span>
                </button>
            </nav>
            <time class="clk" data-kitchen-clock></time>
            <x-theme-toggle class="btn ic" />
            <div class="me">
                <div class="av" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 2)) }}</div>
                <span><b>{{ $user->name }}</b></span>
                <form method="POST" action="{{ route('staff.logout') }}">
                    @csrf
                    <button type="submit" class="btn ic" aria-label="Sign out" title="Sign out">
                        <svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    </button>
                </form>
            </div>
        </header>
        <div class="tick" data-stock-warning role="alert" @if ($lowStockCount === 0) hidden @endif>
            <svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            <span data-stock-warning-text>Warning: {{ $ingredients->filter(fn ($ingredient) => $ingredient->isLowStock())->map(fn ($ingredient) => $ingredient->name.' '.($ingredient->usableStock() <= 0 ? 'out of stock' : 'running low').' ('.$ingredient->usableStock().' usable '.$ingredient->unit.', min '.$ingredient->min_threshold.' '.$ingredient->unit.')')->join(' · ') }}</span>
            <button type="button" data-v="inv">Open stock</button>
        </div>
        <div class="tick" data-refresh-message role="alert" hidden></div>
        @yield('content')
        <div class="live-status" data-live-status aria-live="polite"></div>
        <div class="toast" id="toast" role="status" aria-live="polite"
             data-message="{{ session('error') ?: (session('status') ?: '') }}"></div>
    </div>
</body>
</html>
