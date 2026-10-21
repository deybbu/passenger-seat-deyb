<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Table QR codes · Passenger Seat</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <style>
        body { background: #fff; color: #222; padding: 24px; }
        .print-actions { display: flex; justify-content: space-between; margin: 0 auto 24px; max-width: 960px; }
        .qr-sheet { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; max-width: 960px; margin: 0 auto; }
        .qr-sheet-card { break-inside: avoid; border: 1px solid #bbb; border-radius: 8px; padding: 18px; text-align: center; }
        .qr-sheet-card img { display: block; width: min(100%, 220px); height: auto; margin: 0 auto 12px; }
        .qr-sheet-card h2 { margin: 0; font-size: 24px; }
        .qr-sheet-card p { margin: 4px 0; }
        .qr-sheet-warning { max-width: 960px; margin: 0 auto 20px; }
        @media print {
            body { padding: 0; }
            .print-actions, .qr-sheet-warning { display: none !important; }
            .qr-sheet { max-width: none; grid-template-columns: repeat(3, 1fr); gap: 10mm; }
            .qr-sheet-card { border: 1px solid #888; border-radius: 0; }
        }
        @media (max-width: 700px) {
            .qr-sheet { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <a class="btn" href="{{ route('admin.table-qr') }}">Back to Table QR control</a>
        <button class="btn p" type="button" onclick="window.print()">Print sheet</button>
    </div>

    @unless ($qrUrlConfigured)
        <p class="msg wn qr-sheet-warning" role="alert">
            {{ \App\Support\TableQrUrl::configurationMessage() }}
        </p>
    @endunless

    <main class="qr-sheet">
        @forelse ($tables as $table)
            <article class="qr-sheet-card">
                @if ($qrUrlConfigured && $table->qr_token)
                    <img src="{{ route('admin.api.reference.table-qr.image', ['restaurantTable' => $table->id], false) }}"
                         alt="Scannable order QR code for Table {{ $table->table_number }}">
                @else
                    <p>QR unavailable</p>
                @endif
                <h2>Table {{ $table->table_number }}</h2>
                <p>{{ $table->name }}</p>
            </article>
        @empty
            <p>No tables configured.</p>
        @endforelse
    </main>
</body>
</html>
