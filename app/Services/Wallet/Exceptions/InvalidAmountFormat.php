<?php

namespace App\Services\Wallet\Exceptions;

class InvalidAmountFormat extends WalletException
{
    public function httpStatus(): int
    {
        return 400; // Bad Request: syntactic/format issue
    }
}
