<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RecordSensitiveAction;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            HandleCors::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'audit.sensitive' => RecordSensitiveAction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $exception, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error('Unauthenticated.', 401);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $exception, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error('Validation failed.', 422, $exception->errors());
            }

            return null;
        });

        $exceptions->render(function (Throwable $exception, $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();

                return ApiResponse::error(
                    $status >= 500 ? 'Server error.' : ($exception->getMessage() ?: 'Request failed.'),
                    $status
                );
            }

            report($exception);

            return ApiResponse::error('Server error.', 500);
        });
    })->create();
