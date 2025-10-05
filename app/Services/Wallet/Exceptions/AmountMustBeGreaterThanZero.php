<?php

namespace App\Services\Wallet\Exceptions;

class AmountMustBeGreaterThanZero extends WalletException
{
    public function httpStatus(): int
    {
        return 400; // Bad Request: value fails minimum semantic constraint akin to format
    }
}
