<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f5f3f0;color:#252525;font-family:Arial,sans-serif">
    <div style="max-width:640px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden">
        <header style="padding:24px 32px;background:#8b1e24;color:#fff">
            <div style="font-size:12px;letter-spacing:2px">PASSENGER SEAT</div>
            <h1 style="margin:8px 0 0;font-size:24px">Your event proposal</h1>
        </header>
        <main style="padding:28px 32px;line-height:1.6">
            <div style="white-space:pre-wrap">{{ $messageText }}</div>
            <p style="margin:24px 0">
                <a href="{{ $depositLink }}" style="display:inline-block;padding:13px 20px;background:#8b1e24;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold">View secure deposit details</a>
            </p>
            <p style="font-size:12px;color:#666">This private link expires in seven days. Your reservation remains provisional until confirmed by Passenger Seat.</p>
        </main>
    </div>
</body>
</html>
