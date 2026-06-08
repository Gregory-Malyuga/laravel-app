<?php

use Domains\User\Domain\Exceptions\InvalidCredentialsException;
use Domains\User\Domain\Exceptions\UserForbiddenException;
use Domains\User\Domain\Exceptions\UserInsufficientRoleException;
use Domains\User\Domain\Exceptions\UserNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Shared\Http\Controllers\HealthController;
use Shared\Http\Middleware\AddEsSearchTruncatedHeader;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        then: function (): void {
            Route::middleware('api')
                ->get('/health', HealthController::class);
        },
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->appendToGroup('api', AddEsSearchTruncatedHeader::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (UserInsufficientRoleException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        });
        $exceptions->render(function (InvalidCredentialsException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        });
        $exceptions->render(function (UserForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        });
        $exceptions->render(function (UserNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        });
    })->create();

return $app;
