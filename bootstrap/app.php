<?php

use App\Http\Middleware\BasicAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The URL is public, so everything in the web group needs the basic
        // auth prompt. The health route is registered outside the group and
        // stays open for a quick check from a phone.
        $middleware->appendToGroup('web', BasicAuth::class);

        // Upsun terminates TLS at the router, so trust its forwarded headers
        // and keep generated URLs on https.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
