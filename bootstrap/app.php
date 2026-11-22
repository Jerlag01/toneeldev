<?php

use App\Http\Middleware\BlockIps;
use App\Http\Middleware\RobotsTag;
use App\Http\Middleware\TrackPageView;
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
        // Blocked IP addresses get nothing, not even the login page; public page views are counted.
        $middleware->prepend(BlockIps::class);
        // Payment providers post here without a CSRF token; the status is always re-fetched from their API.
        $middleware->preventRequestForgery(except: ['webhooks/*']);
        // There is no "login" route: guests go to the admin login.
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->web(append: [TrackPageView::class, RobotsTag::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
