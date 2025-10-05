<?php

use App\Services\Wallet\Exceptions\AmountMustBeGreaterThanZero;
use App\Services\Wallet\Exceptions\InvalidAmountFormat;
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (WalletException $e) {
            $status = match (true) {
                $e instanceof InvalidAmountFormat, $e instanceof AmountMustBeGreaterThanZero => 400,
                default => 422,
            };

            return response()->json([
                'error' => $e->getMessage(),
                'type' => class_basename($e),
            ], $status);
        });
    })
    ->create();
