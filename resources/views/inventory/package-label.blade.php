<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
@include('partials.favicon')
<title>Package QR label · Passenger Seat</title>
<style>
*{box-sizing:border-box}body{font:16px Arial,sans-serif;color:#171717;margin:0;padding:24px}.label{width:min(100%,420px);margin:24px auto;padding:24px;text-align:center;border:1px solid #bbb;border-radius:12px}.label svg{display:block;width:260px;max-width:100%;height:auto;margin:12px auto}.label h1{font-size:22px;margin:6px 0}.label p{margin:6px 0;color:#555}.code{font:700 16px monospace;letter-spacing:.04em;color:#111}.actions{text-align:center;margin:20px}@media print{body{padding:0}.label{margin:0;border:0;width:100%;page-break-inside:avoid}.actions{display:none}}
</style>
</head>
<body>
<article class="label">
<h1>{{ $package->ingredient->name }}</h1>
<p>{{ $package->label }} · {{ rtrim(rtrim(number_format((float) $package->package_quantity, 3, '.', ''), '0'), '.') }} {{ $package->ingredient->unit }}</p>
{!! $qrSvg !!}
<p class="code">{{ $package->barcode }}</p>
<p>Manual entry: <span class="code">{{ $package->manual_entry_code }}</span></p>
</article>
<div class="actions"><button type="button" onclick="window.print()">Print label</button> <button type="button" onclick="window.close()">Close</button></div>
</body>
</html>
