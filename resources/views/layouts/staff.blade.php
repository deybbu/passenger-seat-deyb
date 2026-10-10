<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => trim($__env->yieldContent('title', 'Staff'))])
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
    @php
        $user = auth()->user();
        $lowStockCount = $user->hasRole('inventory') ? \App\Models\Ingredient::where('manual_override', false)->whereColumn('current_stock', '<=', 'min_threshold')->count() : 0;
        $links = [
            ['Orders', 'staff.orders.index', 'staff/orders', $user->hasRole('kitchen', 'cashier')],
            ['Walk-in order', 'staff.orders.create', 'staff/orders/new', $user->hasRole('kitchen', 'cashier')],
            ['POS', 'staff.pos.index', 'staff/pos', $user->hasRole('cashier')],
            ['Ledger', 'staff.pos.ledger', 'staff/pos/transactions', $user->hasRole('cashier')],
            ['Inventory', 'inventory.index', 'inventory', $user->hasRole('inventory')],
            ['Stock log', 'inventory.logs', 'inventory/logs', $user->hasRole('inventory')],
        ];
    @endphp

    <header class="border-b-4 border-brand-600 bg-white shadow-sm print:hidden">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <a href="{{ $user->homeUrl() }}" class="flex items-center gap-2">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-600 text-white"><x-icon name="plane" class="h-5 w-5 -rotate-45" /></span>
                    <span class="font-display text-2xl font-bold uppercase tracking-wide text-brand-700">Passenger Seat</span>
                </a>

                <nav class="flex flex-wrap gap-1" aria-label="Staff">
                    @foreach ($links as [$label, $route, $pattern, $allowed])
                        @if ($allowed)
                            @php $active = request()->routeIs($route); @endphp
                            <a href="{{ route($route) }}"
                               class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold {{ $active ? 'bg-brand-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                                {{ $label }}
                                @if ($route === 'inventory.index' && $lowStockCount > 0)
                                    <span class="flash-badge rounded-full bg-red-600 px-2 text-xs font-bold text-white {{ $active ? 'ring-2 ring-white' : '' }}">{{ $lowStockCount }} low</span>
                                @endif
                            </a>
                        @endif
                    @endforeach
                </nav>
            </div>

            <div class="flex items-center gap-3 text-sm">
                <span class="text-gray-600">{{ $user->name }} <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold capitalize">{{ $user->role }}</span></span>
                <form method="POST" action="{{ route('staff.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-gray-300 px-3 py-1.5 font-semibold hover:bg-gray-50">Sign out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        <x-flash />
        @yield('content')
    </main>
</body>
</html>
