<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>Table {{ $tableNumber ?: 'QR' }} · Passenger Seat</title>
    @include('partials.theme-init')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Cormorant+Garamond:wght@500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/qr-order.css', 'resources/css/app-theme.css', 'resources/js/qr-order.js', 'resources/js/app-theme.js'])
</head>
<body class="qr-body">
    <main class="app" id="app">
        <div class="demo">
            <span>Appearance</span>
            <x-theme-toggle />
        </div>
        @if ($invalidTable)
            <div class="center">
                <h2 class="err">Invalid QR code</h2>
                <p class="mute">We couldn't find this table. Please ask our staff for help.</p>
            </div>
        @elseif (! $qrEnabled)
            <div class="center">
                <h2>Customer QR menus are unavailable</h2>
                <p class="mute">Online table ordering is currently disabled. Please ask our staff for help.</p>
            </div>
        @elseif (! $tableEnabled)
            <div class="center">
                <h2>QR ordering is disabled for this table</h2>
                <p class="mute">Please ask our staff for help with your order.</p>
            </div>
        @elseif (! $tableOpen)
            <div class="center">
                <h2>Table {{ $tableNumber }} isn't open yet</h2>
                <p class="mute">Please ask our staff to open your table, then scan the code again. Thank you!</p>
            </div>
        @else
            <header class="hd">
                <div><b>Passenger Seat</b><small>Ordering for</small></div>
                <span class="tb">Table {{ $tableNumber }}</span>
                <button class="myo" id="qr-orders-toggle" type="button" aria-pressed="false">
                    My orders <i id="qr-order-count" hidden></i>
                </button>
            </header>
            <div id="qr-root"
                 data-table="{{ $tableNumber }}"
                 data-table-token="{{ $tableToken }}"
                 data-submission-token="{{ $submissionToken }}"
                 data-place-url="{{ route('customer.qr.place') }}"
                 data-orders-url="{{ route('customer.qr.orders', ['tableSession' => $tableToken]) }}"
                 data-bill-url="{{ route('customer.qr.bill', ['tableSession' => $tableToken]) }}"
                 data-items="{{ json_encode($items) }}"
                 data-categories="{{ json_encode($categories) }}">
                <div id="qr-alert" class="alert" role="status" hidden></div>
                <div id="scr" aria-live="polite"></div>
                <div id="toast" role="status" aria-live="polite"></div>
                <div id="overlay"></div>
            </div>
        @endif
    </main>
</body>
</html>
