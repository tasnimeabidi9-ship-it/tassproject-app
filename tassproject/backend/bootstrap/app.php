<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\VerifyInternalSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'internal.secret' => VerifyInternalSecret::class,
        ]);

        // This is a SPA + JSON API: web guests belong on the SPA login
        // screen, and API guests get a 401 (see withExceptions below)
        // instead of a redirect to a non-existent named route.
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients should always receive JSON (e.g. a 401 for guests)
        // instead of being redirected to a non-existent "login" web route.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
