<?php

namespace App\Services\Wallet;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

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
            if ($idempotencyKey) {
                $existing = $this->transactionModel->newQuery()->where('uuid', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            if ($sender->id === $receiverId) {
                throw new RuntimeException('Cannot transfer to the same user.');
            }

            $amount = $this->normalizeAmount($amount);
            if ($amount === '0.00') {
                throw new RuntimeException('Amount must be greater than zero.');
            }

            $orderedIds = [$sender->id, $receiverId];
            sort($orderedIds, SORT_NUMERIC);

            /** @var array<int, User> $locked */
            $locked = User::whereIn('id', $orderedIds)->lockForUpdate()->get()->keyBy('id')->all();

            if (! isset($locked[$sender->id])) {
                throw new RuntimeException('Sender not found.');
            }
            if (! isset($locked[$receiverId])) {
                throw new RuntimeException('Receiver not found.');
            }

            /** @var User $lockedSender */
            $lockedSender =  [$sender->id];
            /** @var User $lockedReceiver */
            $lockedReceiver = $locked[$receiverId];

            $amountCents = $this->toCents($amount);
            $senderBalanceCents = $this->toCents($this->normalizeAmount((string) $lockedSender->balance));
            $receiverBalanceCents = $this->toCents($this->normalizeAmount((string) $lockedReceiver->balance));

            $commissionCents = $this->calculateCommissionCents($amountCents);
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

            /** @var Transaction $transaction */
            $transaction = $this->transactionModel->newQuery()->create([
                'uuid' => $uuid,
                'sender_id' => $lockedSender->id,
                'receiver_id' => $lockedReceiver->id,
                'amount' => $amount, // original normalized amount
                'commission_fee' => $commission,
                'status' => 'success',
            ]);

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
            throw new RuntimeException('Invalid amount format.');
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

        return ((int) $int) * 100 + (int) $frac;
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
        $numerator = $amountCents * 15;

        return intdiv($numerator + 500, 1000);
    }
}
