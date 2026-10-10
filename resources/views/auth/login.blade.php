<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => 'Staff check-in'])
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="grid min-h-screen place-items-center bg-gray-100 px-4 font-sans text-gray-900 antialiased">
    <main class="w-full max-w-sm">
        <section class="pass overflow-hidden">
            <div class="bg-brand-600 px-5 py-4 text-white">
                <p class="text-xs font-semibold uppercase tracking-widest text-brand-100">Passenger Seat</p>
                <h1 class="font-display text-4xl font-bold uppercase leading-none">Staff check-in</h1>
            </div>

            <form method="POST" action="{{ route('login.attempt') }}" class="p-5">
                @csrf
                <x-flash />

                <label for="email" class="mb-1 block text-sm font-semibold">Email</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">

                <label for="password" class="mb-1 mt-4 block text-sm font-semibold">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">

                <label class="mt-4 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remember" value="1" class="accent-brand-600"> Keep me signed in
                </label>

                <button type="submit" class="mt-5 w-full rounded-full bg-brand-600 px-6 py-3 font-bold text-white hover:bg-brand-700 focus:ring-4 focus:ring-brand-100">Sign in</button>
            </form>
        </section>
    </main>
</body>
</html>
