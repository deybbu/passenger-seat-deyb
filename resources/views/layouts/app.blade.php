<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/passenger seat favicon.png') }}">
    <title>@yield('title', 'Passenger Seat Restaurant') | Passenger Seat</title>
    <meta name="description" content="A Filipino-inspired dining experience at Passenger Seat Restaurant.">
    <script>
        const savedTheme = window.localStorage.getItem('passenger-seat-theme');
        document.documentElement.dataset.theme = savedTheme === 'dark' ? 'dark' : 'light';
    </script>
    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="landing-site">
    <x-landing.navigation />

    <main>
        @yield('content')
    </main>

    <x-landing.visit />
    <x-landing.footer />
    @stack('scripts')
</body>
</html>
