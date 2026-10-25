<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => 'Staff check-in'])
    <meta name="robots" content="noindex,nofollow">
    <style>
        .staff-login-page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 36px 20px;
            background:
                radial-gradient(ellipse at 90% 8%, rgba(198, 40, 40, .1), transparent 32%),
                linear-gradient(135deg, #fff 15%, #fcfcfd 72%, #fff8f8);
        }

        .staff-login-shell {
            width: min(100%, 960px);
        }

        .staff-login-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            border: 1px solid rgba(229, 231, 235, .9);
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 25px 65px rgba(50, 20, 20, .12);
        }

        .staff-login-visual {
            position: relative;
            display: flex;
            min-height: 560px;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            padding: 34px;
            color: #fff;
            background: #7d1717;
        }

        .staff-login-visual::before {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(42, 8, 8, .2), rgba(42, 8, 8, .82));
            content: "";
        }

        .staff-login-visual img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .staff-login-visual > *:not(img) {
            position: relative;
            z-index: 1;
        }

        .staff-login-brand {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            gap: 11px;
            color: #fff;
        }

        .staff-login-brand .brand-mark {
            background: linear-gradient(140deg, #e53935, #a61b1b);
        }

        .staff-login-brand .brand-copy small {
            color: rgba(255, 255, 255, .75);
        }

        .staff-login-visual-copy {
            max-width: 350px;
        }

        .staff-login-kicker {
            color: #ffd7d7;
            font-size: 10px;
            font-weight: 850;
            letter-spacing: 2px;
        }

        .staff-login-visual h2 {
            margin: 12px 0;
            font-size: clamp(36px, 5vw, 54px);
            font-weight: 850;
            letter-spacing: -2px;
            line-height: 1.05;
        }

        .staff-login-visual h2 em {
            color: #ffd0d0;
            font-family: Georgia, "Times New Roman", serif;
            font-weight: 500;
        }

        .staff-login-visual-copy p {
            margin: 0;
            color: rgba(255, 255, 255, .82);
            font-size: 13px;
            line-height: 1.9;
        }

        .staff-login-form-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(30px, 5vw, 58px);
        }

        .staff-login-form-panel .eyebrow {
            margin-bottom: 13px;
        }

        .staff-login-form-panel h1 {
            margin: 0;
            font-size: clamp(30px, 4vw, 40px);
            font-weight: 850;
            letter-spacing: -1.8px;
            line-height: 1.1;
        }

        .staff-login-intro {
            margin: 12px 0 27px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .staff-login-form label {
            display: block;
            margin: 17px 0 7px;
            font-size: 11px;
            font-weight: 750;
        }

        .staff-login-form input[type="email"],
        .staff-login-form input[type="password"] {
            width: 100%;
            min-height: 46px;
            padding: 11px 13px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-size: 12px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .staff-login-form input[type="email"]:focus,
        .staff-login-form input[type="password"]:focus {
            border-color: var(--red);
            box-shadow: 0 0 0 4px rgba(198, 40, 40, .1);
        }

        .staff-login-remember {
            display: flex !important;
            align-items: center;
            gap: 9px;
            color: #52525b;
            font-weight: 600 !important;
        }

        .staff-login-remember input {
            width: 15px;
            height: 15px;
            accent-color: var(--red);
        }

        .staff-login-submit {
            width: 100%;
            margin-top: 22px;
            cursor: pointer;
        }

        .staff-login-back {
            margin-top: 22px;
            color: var(--muted);
            font-size: 10px;
            text-align: center;
        }

        .staff-login-back a {
            color: var(--red);
            font-weight: 750;
        }

        @media (max-width: 800px) {
            .staff-login-page {
                padding: 18px 14px;
            }

            .staff-login-card {
                grid-template-columns: 1fr;
            }

            .staff-login-visual {
                min-height: 225px;
                padding: 23px;
            }

            .staff-login-visual h2 {
                margin-bottom: 5px;
                font-size: 34px;
            }

            .staff-login-visual-copy p {
                display: none;
            }

            .staff-login-form-panel {
                padding: 30px 24px;
            }
        }
    

/* Remove decorative shadows across the interface while keeping focus and scanner overlays visible. */
*:not(.admin-scanner-corners) { box-shadow: none !important; text-shadow: none !important; }
*:focus-visible { outline: 2px solid var(--red, currentColor) !important; outline-offset: 2px !important; }
</style>
</head>
<body class="staff-login-page">
    <x-theme-toggle class="staff-login-theme-toggle" />
    <main class="staff-login-shell">
        <section class="staff-login-card" aria-labelledby="staff-login-title">
            <div class="staff-login-visual">
                <img src="{{ asset('images/passenger-seat-hero.jpg') }}" alt="" fetchpriority="high">
                <a class="staff-login-brand" href="{{ route('home') }}" aria-label="Passenger Seat home">
                    <span class="brand-mark" aria-hidden="true">PS</span>
                    <span class="brand-copy"><strong>PASSENGER SEAT</strong><small>RESTAURANT</small></span>
                </a>

                <div class="staff-login-visual-copy">
                    <span class="staff-login-kicker">PASSENGER SEAT · STAFF</span>
                    <h2>Welcome<br><em>aboard.</em></h2>
                    <p>Sign in to continue to your team workspace.</p>
                </div>
            </div>

            <div class="staff-login-form-panel">
                <div class="eyebrow"><span class="eyebrow-line"></span> STAFF ACCESS</div>
                <h1 id="staff-login-title">Staff check-in</h1>
                <p class="staff-login-intro">Use your staff account to securely access your workspace.</p>

                <form method="POST" action="{{ route('staff.login.attempt') }}" class="staff-login-form">
                    @csrf
                    <x-flash />

                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}">

                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password">

                    <label class="staff-login-remember">
                        <input type="checkbox" name="remember" value="1">
                        Keep me signed in
                    </label>

                    <button type="submit" class="button staff-login-submit">Sign in <span aria-hidden="true">↗</span></button>
                </form>

                <p class="staff-login-back"><a href="{{ route('home') }}">← Back to the public site</a></p>
            </div>
        </section>
    </main>
</body>
</html>
