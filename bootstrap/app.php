<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();
