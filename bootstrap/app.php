<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'audit.sensitive' => \App\Http\Middleware\RecordSensitiveAction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $exception, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return \App\Support\ApiResponse::error('Unauthenticated.', 401);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $exception, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return \App\Support\ApiResponse::error('Validation failed.', 422, $exception->errors());
            }

            return null;
        });

        $exceptions->render(function (\Throwable $exception, $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();

                return \App\Support\ApiResponse::error(
                    $status >= 500 ? 'Server error.' : ($exception->getMessage() ?: 'Request failed.'),
                    $status
                );
            }

            report($exception);

            return \App\Support\ApiResponse::error('Server error.', 500);
        });
    })->create();
