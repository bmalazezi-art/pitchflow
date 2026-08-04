<?php

use App\Console\Commands\ResetDemoData;
use App\Http\Middleware\EnsureOrganizationApproved;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoCacheForAuthenticatedPages;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ResetDemoData::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'no.cache.auth' => NoCacheForAuthenticatedPages::class,
            'organization.approved' => EnsureOrganizationApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            if ($request->is('login') || $request->is('forgot-password')) {
                return back()->withErrors(['email' => __('messages.too_many_login_attempts')]);
            }

            return null;
        });
    })->create();
