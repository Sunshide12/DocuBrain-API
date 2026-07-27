<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // GraphQL declares its own nullability. Letting Laravel rewrite ""  to null
        // turns a whitespace-only message into a null for a String! argument, and the
        // user gets a raw schema error ("Variable $m of non-null type String! must not
        // be null") instead of a normal reply. Keep the empty string and let the
        // resolver's validation produce a human message.
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request) => $request->is('graphql'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('broadcasting/*'),
        );
    })->create();
