<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('staff.login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() === 419 && $request->isMethod('POST') && $request->is('events')) {
                $message = 'Your inquiry form expired before it was submitted. Please review your details and submit it again.';

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 419);
                }

                return redirect()
                    ->back()
                    ->withInput($request->except('_token'))
                    ->withErrors(['inquiry' => $message]);
            }

            if ($exception->getStatusCode() === 419 && $request->isMethod('POST')) {
                if ($request->is('staff/logout') || $request->is('staff/inventory/logout')) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('staff.login');
                }

                if ($request->is('staff/login')) {
                    return redirect()
                        ->route('staff.login')
                        ->withErrors(['email' => 'Your login page expired. Please try again.']);
                }
            }

            return null;
        });
    })->create();
