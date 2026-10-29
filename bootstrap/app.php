<?php

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
        $middleware->redirectGuestsTo(fn () => route('admin.login'));

        // Local tunnels (cloudflared) forward HTTPS traffic to http://localhost; trust their
        // X-Forwarded-* headers so generated asset/form URLs keep https. Only loopback is trusted.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->alias([
            'admin.active' => \App\Http\Middleware\EnsureAdminIsActive::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/whatsapp',
            'webhooks/twilio',
            'webhooks/twilio/status',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
