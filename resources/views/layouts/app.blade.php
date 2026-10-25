<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>@yield('title', 'Passenger Seat Restaurant') | Passenger Seat</title>
    <meta name="description" content="A Filipino-inspired dining experience at Passenger Seat Restaurant.">
    @include('partials.theme-init')
    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/css/app-theme.css', 'resources/js/app.js', 'resources/js/app-theme.js'])
</head>
<body class="landing-site app-theme-scope">
    <x-landing.navigation />

    <main>
        @yield('content')
    </main>

    <x-landing.visit />
    <x-landing.footer />
    @stack('scripts')
</body>
</html>
