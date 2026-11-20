<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'hr' => \App\Http\Middleware\HrPortal::class,
            'role' => \App\Http\Middleware\RequireRole::class,
        ]);
        // Online hosts (Railway, Render, …) end HTTPS at their proxy; trust it so URLs and secure cookies are right.
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Hosts like Vercel cut long log lines, which hides the message at the start of a stack trace.
        // Also write one short line per error so the cause is always readable.
        $short = $exceptions->report(function (\Throwable $e): void {
            error_log('[error] '.get_class($e).': '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
        });
        // Vercel keeps only the end of a request's output, so skip the long stack trace there.
        if (getenv('VERCEL')) {
            $short->stop();
        }
    })->create();
