<?php

namespace App\Services\Wallet;

use App\Models\Transaction;
use App\Models\User;

/**
 * Service responsible for handling balance transfers between users.
 *
 * Responsibilities (planned):
 * - Perform atomic balance transfer with commission fee (1.5% rounded half-up)
 * - Ensure idempotency using provided idempotency key (future implementation)
 * - Record a Transaction row with status (success or failed)
 */
class TransferService
{
    public function __construct(
        private readonly Transaction $transactionModel,
    ) {
        // Intentionally empty.
    }

    /**
     * Transfer funds from one user (sender) to another (receiver) applying commission.
     *
     * Parameters:
     * - $sender: initiating user whose balance will be debited.
     * - $receiverId: id of the user receiving funds.
     * - $amount: decimal string (scale 2) representing transfer amount (excluding commission).
     * - $idempotencyKey: optional unique key to make retries safe.
     *
     * Returns a Transaction model instance (placeholder; not yet persisted).
     */
    public function transfer(User $sender, int $receiverId, string $amount, ?string $idempotencyKey = null): Transaction
    {
        /* Planned implementation steps:
         * 1. Begin DB transaction.
         * 2. If idempotency key provided: check existing processed transaction and return it if found.
         * 3. Select & lock sender and receiver rows (order by user id to avoid deadlocks).
         * 4. Validate sender != receiver and amount > 0; normalize amount to scale=2.
         * 5. Calculate commission = amount * 1.5% (round HALF_UP to 2 decimal places).
         * 6. Total debit = amount + commission; assert sender balance >= total debit.
         * 7. Apply balance updates (sender -= total debit; receiver += amount).
         * 8. Persist transaction record with uuid, participants, amount, commission, status=success.
         * 9. Commit transaction.
         * 10. On any failure: rollback and optionally record failed transaction (status=failed) then rethrow domain exception.
         * Precision: use BCMath / Brick\Math for exact decimal operations.
         * Concurrency: FOR UPDATE locks + idempotency prevent double spend.
         */

        // Placeholder implementation: return a new unsaved transaction instance for now.
        return $this->transactionModel->newInstance();
    }
}
