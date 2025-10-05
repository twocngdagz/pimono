<?php

namespace App\Services\Wallet\Exceptions;

class IdempotencyConflict extends WalletException
{
    public function httpStatus(): int
    {
        return 409; // Conflict: same idempotency key reused with different parameters
    }
}
