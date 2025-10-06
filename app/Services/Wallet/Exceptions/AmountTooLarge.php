<?php

namespace App\Services\Wallet\Exceptions;

class AmountTooLarge extends WalletException
{
    public function httpStatus(): int
    {
        return 400;
    }
}
