<?php

namespace App\Services\Wallet;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\Exceptions\AmountMustBeGreaterThanZero;
use App\Services\Wallet\Exceptions\AmountTooLarge;
use App\Services\Wallet\Exceptions\CannotTransferToSelf;
use App\Services\Wallet\Exceptions\IdempotencyConflict;
use App\Services\Wallet\Exceptions\InsufficientFunds;
use App\Services\Wallet\Exceptions\InvalidAmountFormat;
use App\Services\Wallet\Exceptions\ReceiverNotFound;
use App\Services\Wallet\Exceptions\SenderNotFound;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferService
{
    public function __construct(
        private readonly Transaction $transactionModel,
    ) {}

    /**
     * Transfer funds from one user (sender) to another (receiver) applying commission.
     *
     * Parameters:
     * - $sender: initiating user whose balance will be debited.
     * - $receiverId: id of the user receiving funds.
     * - $amount: decimal string (scale 2) representing transfer amount (excluding commission).
     * - $idempotencyKey: optional unique key to make retries safe.
     *
     * Returns: persisted Transaction model instance.
     */
    public function transfer(User $sender, int $receiverId, string $amount, ?string $idempotencyKey = null): Transaction
    {
        return DB::transaction(function () use ($sender, $receiverId, $amount, $idempotencyKey) {
            if ($sender->id === $receiverId) {
                throw new CannotTransferToSelf('Cannot transfer to the same user.');
            }

            $amount = $this->normalizeAmount($amount);
            if ($amount === '0.00') {
                throw new AmountMustBeGreaterThanZero('Amount must be greater than zero.');
            }
            if ($idempotencyKey) {
                $existing = $this->transactionModel->newQuery()->where('uuid', $idempotencyKey)->first();
                if ($existing) {
                    $existingAmountNormalized = $this->normalizeAmount((string) $existing->amount);
                    $same = ($existing->sender_id === $sender->id)
                        && ($existing->receiver_id === $receiverId)
                        && ($existingAmountNormalized === $amount);
                    if ($same) {
                        return $existing;
                    }
                    throw new IdempotencyConflict('Idempotency key reused with different parameters.');
                }
            }

            $orderedIds = [$sender->id, $receiverId];
            sort($orderedIds, SORT_NUMERIC);

            $locked = User::whereIn('id', $orderedIds)->lockForUpdate()->get()->keyBy('id')->all();
            if (! isset($locked[$sender->id])) {
                throw new SenderNotFound('Sender not found.');
            }
            if (! isset($locked[$receiverId])) {
                throw new ReceiverNotFound('Receiver not found.');
            }
            $lockedSender = $locked[$sender->id];
            $lockedReceiver = $locked[$receiverId];

            $senderBalanceNormalized = $this->normalizeAmount((string) $lockedSender->balance);
            if ($this->compareNormalizedAmounts($amount, $senderBalanceNormalized) === 1) {
                throw new InsufficientFunds('Insufficient balance to perform transfer.');
            }

            $this->assertRepresentableOrThrow($amount);

            $amountCents = $this->toCents($amount);
            $senderBalanceCents = $this->toCents($senderBalanceNormalized);
            $receiverBalanceCents = $this->toCents($this->normalizeAmount((string) $lockedReceiver->balance));

            $commissionCents = $this->calculateCommissionCents($amountCents);

            // Check for addition overflow (amount + commission) exceeding PHP_INT_MAX
            if ($commissionCents > PHP_INT_MAX - $amountCents) {
                throw new AmountTooLarge('Amount exceeds maximum representable value.');
            }
            $commission = $this->centsToString($commissionCents);
            $totalDebitCents = $amountCents + $commissionCents;
            if ($senderBalanceCents < $totalDebitCents) {
                throw new InsufficientFunds('Insufficient balance to perform transfer.');
            }

            $senderBalanceCents -= $totalDebitCents;
            $receiverBalanceCents += $amountCents;
            $lockedSender->balance = $this->centsToString($senderBalanceCents);
            $lockedReceiver->balance = $this->centsToString($receiverBalanceCents);
            $lockedSender->save();
            $lockedReceiver->save();

            $uuid = $idempotencyKey ?: (string) Str::uuid();

            try {
                $transaction = $this->transactionModel->newQuery()->create([
                    'uuid' => $uuid,
                    'sender_id' => $lockedSender->id,
                    'receiver_id' => $lockedReceiver->id,
                    'amount' => $amount,
                    'commission_fee' => $commission,
                    'status' => 'success',
                ]);
            } catch (QueryException $qe) {
                // Recover from potential race on unique uuid (idempotency) creation
                if ($idempotencyKey) {
                    $existing = $this->transactionModel->newQuery()->where('uuid', $idempotencyKey)->first();
                    if ($existing) {
                        $existingAmountNormalized = $this->normalizeAmount((string) $existing->amount);
                        $same = ($existing->sender_id === $lockedSender->id)
                            && ($existing->receiver_id === $lockedReceiver->id)
                            && ($existingAmountNormalized === $amount);
                        if ($same) {
                            return $existing;
                        }
                        throw new IdempotencyConflict('Idempotency key reused with different parameters.');
                    }
                }
                throw $qe;
            }

            return $transaction;
        });
    }

    /**
     * Normalize input amount to strict scale=2 decimal string.
     */
    private function normalizeAmount(string $amount): string
    {
        $amount = trim($amount);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidAmountFormat('Invalid amount format.');
        }
        if (! str_contains($amount, '.')) {
            return $amount.'.00';
        }
        [$int, $frac] = explode('.', $amount, 2);
        $frac = str_pad($frac, 2, '0');

        return $int.'.'.substr($frac, 0, 2);
    }

    /**
     * Convert decimal string (scale=2) to integer cents.
     */
    private function toCents(string $amount): int
    {
        [$int, $frac] = explode('.', $amount, 2);
        $centsStr = $int.$frac;
        $maxStr = (string) PHP_INT_MAX;
        if (strlen($centsStr) < strlen($maxStr) || (strlen($centsStr) === strlen($maxStr) && $centsStr <= $maxStr)) {
            return (int) $centsStr;
        }

        throw new AmountTooLarge('Amount exceeds maximum representable value.');
    }

    /**
     * Convert integer cents back to string with 2 decimals.
     */
    private function centsToString(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $int = intdiv($cents, 100);
        $frac = $cents % 100;
        $str = $int.'.'.str_pad((string) $frac, 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$str : $str;
    }

    /**
     * Commission cents = amount_cents * 1.5% (15/1000) rounded half up to nearest cent.
     */
    private function calculateCommissionCents(int $amountCents): int
    {

        $q = intdiv($amountCents, 1000);
        $r = $amountCents - ($q * 1000);

        $partial = $r * 15 + 500;

        return $q * 15 + intdiv($partial, 1000);
    }

    /**
     * Compare two normalized (scale=2) decimal strings.
     * Returns -1 if a<b, 0 if equal, 1 if a>b.
     */
    private function compareNormalizedAmounts(string $a, string $b): int
    {
        [$ai, $af] = explode('.', $a, 2);
        [$bi, $bf] = explode('.', $b, 2);

        $lenCmp = strlen($ai) <=> strlen($bi);
        if ($lenCmp !== 0) {
            return $lenCmp;
        }
        $intCmp = strcmp($ai, $bi);
        if ($intCmp !== 0) {
            return $intCmp < 0 ? -1 : 1;
        }
        $fracCmp = strcmp($af, $bf);
        if ($fracCmp === 0) {
            return 0;
        }

        return $fracCmp < 0 ? -1 : 1;
    }

    /**
     * Ensure amount can be represented internally in integer cents without 64-bit overflow.
     * Throws AmountTooLarge only if caller already confirmed sender can afford the raw amount.
     */
    private function assertRepresentableOrThrow(string $amount): void
    {
        [$int, $frac] = explode('.', $amount, 2);
        $limitIntPart = intdiv(PHP_INT_MAX, 100);
        $remainder = PHP_INT_MAX - ($limitIntPart * 100);

        $limitStr = (string) $limitIntPart;
        if (strlen($int) < strlen($limitStr)) {
            return;
        }
        if (strlen($int) > strlen($limitStr)) {
            throw new AmountTooLarge('Amount exceeds maximum representable value.');
        }
        if ($int < $limitStr) {
            return; // smaller integer part => safe
        }
        if ($int > $limitStr) {
            throw new AmountTooLarge('Amount exceeds maximum representable value.');
        }

        $fracInt = (int) $frac;
        if ($fracInt > $remainder) {
            throw new AmountTooLarge('Amount exceeds maximum representable value.');
        }
    }
}
