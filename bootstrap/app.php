<?php

use App\Exceptions\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Force JSON responses for /api/* requests, regardless of Accept
         * header. Without this an exception on a non-JSON-aware client
         * would render Laravel's HTML debug page.
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        /*
         * All API exception rendering is delegated to a dedicated class —
         * see App\Exceptions\ApiExceptionRenderer. Keeping bootstrap
         * thin makes the renderer testable in isolation.
         */
        $exceptions->render(new ApiExceptionRenderer());
    })->create();
