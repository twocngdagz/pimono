<?php

namespace App\Services\Wallet\Exceptions;

use Illuminate\Support\Str;
use RuntimeException;

abstract class WalletException extends RuntimeException
{
    // Default HTTP status for domain rule violations (overridden in subclasses as needed)
    public function httpStatus(): int
    {
        return 422;
    }

    // Machine-readable code (stable, namespaced)
    public function errorCode(): string
    {
        return 'wallet.'.Str::snake(class_basename(static::class));
    }
}
