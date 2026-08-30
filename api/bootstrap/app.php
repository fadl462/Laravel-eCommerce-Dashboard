<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\SetLocaleFromHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
|--------------------------------------------------------------------------
| Reference bootstrap/app.php
|--------------------------------------------------------------------------
| `laravel new` generates this file already — don't overwrite it wholesale.
| The only two things this project needs added to YOUR copy are:
|   1. the two middleware aliases registered in ->withMiddleware()
|   2. api routes wired with the 'api' prefix + sanctum stateless guard
| Everything else below is the standard Laravel 11 skeleton shown for context.
*/
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Route middleware aliases used throughout routes/api.php:
        //   ->middleware('permission:products.edit')
        //   ->middleware('locale')
        $middleware->alias([
            'permission' => CheckPermission::class,
            'locale' => SetLocaleFromHeader::class,
        ]);

        // Sanctum's stateful-domain check only matters if the frontend ever
        // authenticates via cookies (a same-origin SPA build). This project's
        // AuthController issues Bearer tokens instead, so the default 'api'
        // middleware group (throttle + bindings) is sufficient as shipped —
        // no changes needed here beyond the alias registration above.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return clean JSON (not an HTML error page) for every API exception —
        // important since this backend has no Blade views, it's API-only.
        $exceptions->shouldRenderJsonWhen(fn ($request) => true);
    })->create();
