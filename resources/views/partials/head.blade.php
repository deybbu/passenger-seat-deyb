<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@include('partials.favicon')
<title>{{ $title ?? 'Menu' }} | Passenger Seat Restaurant</title>
@include('partials.theme-init')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/css/app-theme.css', 'resources/js/app.js', 'resources/js/app-theme.js'])
