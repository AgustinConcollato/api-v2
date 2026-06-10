<?php

use App\Exceptions\InsufficientStockException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        });

        $exceptions->render(function (ModelNotFoundException $e) {
            return response()->json(['error' => 'Recurso no encontrado.'], 404);
        });

        $exceptions->render(function (InsufficientStockException $e) {
            return response()->json([
                'message'      => 'Algunos productos se quedaron sin stock mientras finalizabas la compra.',
                'stock_errors' => $e->errors,
            ], 409);
        });

        $exceptions->render(function (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        });
    })->create();
