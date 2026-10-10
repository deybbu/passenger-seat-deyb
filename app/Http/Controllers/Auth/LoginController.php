<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect($request->user()->homeUrl());
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Those details do not match our records.']);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user === null || ! in_array($user->role, User::ROLES, true)) {
            $this->endSession($request);

            return redirect()->route('staff.login')->withErrors(['email' => 'This account has no staff role yet. Ask an admin.']);
        }

        return redirect()->intended($user->homeUrl());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->endSession($request);

        return redirect()->route('staff.login');
    }

    private function endSession(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
