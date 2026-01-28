<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('mp:refresh-token')->daily();
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('v1/*') || $request->expectsJson()) {
                return null;
            }

            return route('login');
        });

        $middleware->appendToGroup('api', [\App\Http\Middleware\SetTenantBranch::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request): bool {
            if ($request->is('v1/*')) {
                return true;
            }

            return $request->expectsJson();
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if (! $request->is('v1/*') && ! $request->expectsJson()) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                return response()->json([
                    'message' => 'Registro não encontrado.',
                ], 404);
            }

            return response()->json([
                'message' => 'Registro ou rota não encontrada.',
            ], 404);
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if (! $request->is('v1/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->is('v1/*') && ! $request->expectsJson()) {
                return null;
            }

            if (config('app.debug')) {
                return null;
            }

            return response()->json([
                'message' => 'Erro interno do servidor.',
            ], 500);
        });
    })
    ->create();
