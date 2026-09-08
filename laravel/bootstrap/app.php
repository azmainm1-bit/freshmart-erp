<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // routes/api.php is required from routes/web.php, not registered
        // here — these JSON endpoints authenticate via the same session
        // cookie as the Inertia app (this is a single SPA, not a separate
        // token-auth API client), so they need the 'web' middleware group
        // (session + CSRF), not Laravel's stateless default 'api' group.
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            EnsureActiveUser::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // BusinessRuleException (InsufficientStock, ApprovalRequired,
        // IdempotencyConflict, etc.) always renders as structured JSON —
        // the transactional POS/inventory/purchasing endpoints are JSON
        // endpoints (routes/api.php), not classic Inertia form posts, so
        // the frontend can inspect `error`/`details` precisely. See
        // docs/ARCHITECTURE.md for why these are split from the Inertia page
        // routes in routes/web.php.
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            return response()->json($e->toResponseArray(), $e->status);
        });
    })->create();
