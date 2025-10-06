<?php

use App\Http\Middleware\RequestIdMiddleware;
use App\Services\Wallet\Exceptions\WalletException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(RequestIdMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (WalletException $e, $request) {
            $status = $e->httpStatus();
            $requestId = $request->attributes->get('request_id');

            return response()->json([
                'error' => $e->getMessage(),
                'type' => class_basename($e),
                'code' => $e->errorCode(),
                'request_id' => $requestId,
            ], $status)->header('X-Request-ID', $requestId);
        });
    })
    ->create();
