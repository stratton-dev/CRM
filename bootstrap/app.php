<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'supabase'  => \App\Http\Middleware\SupabaseAuthenticate::class,
        ]);
        // Cap per_page on every API request (DoS / memory guard).
        $middleware->appendToGroup('api', \App\Http\Middleware\ClampPerPage::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
