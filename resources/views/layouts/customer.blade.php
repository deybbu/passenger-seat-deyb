<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>@yield('title', 'Passenger Seat Restaurant') · Passenger Seat</title>
    @include('partials.theme-init')
    @vite(['resources/css/app.css', 'resources/css/app-theme.css', 'resources/js/app.js', 'resources/js/app-theme.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-red-100 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('menu') }}" class="flex items-center gap-3" aria-label="Passenger Seat home">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-red-700 text-lg font-black text-white">PS</span>
                <span>
                    <span class="block text-lg font-black tracking-tight">PASSENGER SEAT</span>
                    <span class="block text-xs font-semibold uppercase tracking-[.22em] text-red-700">Restaurant · Boarding now</span>
                </span>
            </a>
            <nav class="flex items-center gap-3">
                <a href="{{ route('menu') }}" class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-red-50 hover:text-red-800 sm:inline">Menu</a>
                <a href="{{ route('cart.show') }}" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Cart <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5">{{ collect(session('cart', []))->sum() }}</span>
                </a>
                <x-theme-toggle class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" />
            </nav>
        </div>
    </header>

    @if (session('success') || session('error'))
        <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <div role="status" class="rounded-xl border {{ session('error') ? 'border-red-300 bg-red-50 text-red-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900' }} px-4 py-3 text-sm font-medium">
                {{ session('success') ?? session('error') }}
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
            <div role="alert" class="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
                <p class="font-bold">Please check the following:</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <main>@yield('content')</main>

    <footer class="mt-16 border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-8 text-sm text-slate-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
            <p class="font-semibold text-slate-700">Passenger Seat Restaurant</p>
            <p>Good food. Smooth journey. Please proceed to the counter for payment.</p>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
