<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Passenger Seat is an airplane-themed restaurant on the 3rd Floor of SM City San Mateo, Rizal. Boodle fights, private events and reservations. Open daily 10 AM to 9 PM.">
    @include('partials.favicon')
    <title>@yield('title', 'Passenger Seat Restaurant')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing-design.css') }}">
</head>
<body>
    @include('landing.partials.navigation')

    <main>
        @yield('content')
    </main>

    @include('landing.partials.footer')
    <nav class="sb" aria-label="Quick actions">
        <a class="btn" href="{{ route('menu') }}">Order</a>
        <a class="btn red" href="#reserve">Reserve</a>
    </nav>
    <div id="toast" role="status" aria-live="polite"></div>

    <script src="{{ asset('js/landing-design.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
