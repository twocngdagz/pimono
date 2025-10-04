<?php

use App\Models\User;
use App\Services\Wallet\Exceptions\AmountMustBeGreaterThanZero;
use App\Services\Wallet\Exceptions\CannotTransferToSelf;
use App\Services\Wallet\Exceptions\IdempotencyConflict;
use App\Services\Wallet\Exceptions\InsufficientFunds;
use App\Services\Wallet\Exceptions\InvalidAmountFormat;
use App\Services\Wallet\Exceptions\ReceiverNotFound;
use App\Services\Wallet\Exceptions\SenderNotFound;
use App\Services\Wallet\Exceptions\WalletException;
use App\Services\Wallet\TransferService;
use Illuminate\Support\Str;

it('detects idempotency conflict when parameters differ', function () {
    $sender = User::factory()->create(['balance' => '500.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);

    /** @var TransferService $service */
    $service = app(TransferService::class);
    $key = (string) Str::uuid();

    $service->transfer($sender, $receiver->id, '50.00', $key);

    // Change amount with same key should conflict
    expect(fn () => $service->transfer($sender->fresh(), $receiver->id, '60.00', $key))
        ->toThrow(IdempotencyConflict::class);
});

it('all domain exceptions extend WalletException', function () {
    $classes = [
        AmountMustBeGreaterThanZero::class,
        CannotTransferToSelf::class,
        IdempotencyConflict::class,
        InsufficientFunds::class,
        InvalidAmountFormat::class,
        ReceiverNotFound::class,
        SenderNotFound::class,
    ];

    foreach ($classes as $cls) {
        $e = new $cls('x');
        expect($e)->toBeInstanceOf(WalletException::class);
    }
});

it('handles smallest non-zero amount with zero commission rounding', function () {
    $sender = User::factory()->create(['balance' => '1.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);

    /** @var TransferService $service */
    $service = app(TransferService::class);

    $tx = $service->transfer($sender, $receiver->id, '0.01');

    $sender->refresh();
    $receiver->refresh();

    // Commission: 0.01 * 1.5% = 0.00015 => rounds to 0.00
    expect($tx->commission_fee)->toBe('0.00');
    expect($sender->balance)->toBe('0.99'); // debited only amount
    expect($receiver->balance)->toBe('0.01');
});

it('rounds commission half-up properly for 0.67 amount', function () {
    $sender = User::factory()->create(['balance' => '10.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);

    /** @var TransferService $service */
    $service = app(TransferService::class);

    $tx = $service->transfer($sender, $receiver->id, '0.67');

    $sender->refresh();
    $receiver->refresh();

    // 0.67 * 1.5% = 0.01005 => 0.01 commission
    expect($tx->commission_fee)->toBe('0.01');
    expect($sender->balance)->toBe('9.32'); // 10.00 - (0.67 + 0.01) = 9.32
    expect($receiver->balance)->toBe('0.67');
});

it('handles large high-precision amount within schema limits', function () {
    $sender = User::factory()->create(['balance' => '200000000000000.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);

    $amount = '100000000000000.00'; // 1e14, safe for commission math
    /** @var TransferService $service */
    $service = app(TransferService::class);

    $tx = $service->transfer($sender, $receiver->id, $amount);

    $sender->refresh();
    $receiver->refresh();

    // Commission = amount * 1.5% = 1,500,000,000,000.00
    expect($tx->commission_fee)->toBe('1500000000000.00');
    expect($sender->balance)->toBe('98500000000000.00'); // 200000000000000 - 100000000000000 - 1500000000000
    expect($receiver->balance)->toBe($amount);
});

it('stress test alternating transfers without deadlocks', function () {
    $a = User::factory()->create(['balance' => '1000.00']);
    $b = User::factory()->create(['balance' => '1000.00']);

    /** @var TransferService $service */
    $service = app(TransferService::class);

    $iterations = 200; // moderate loop for performance
    $amount = '1.00';

    for ($i = 0; $i < $iterations; $i++) {
        if ($i % 2 === 0) {
            $service->transfer($a, $b->id, $amount);
        } else {
            $service->transfer($b, $a->id, $amount);
        }
        $a->refresh();
        $b->refresh();
    }

    // Because of commission, balances will have decreased (commission paid each iteration), but total sum predictable.
    // Compute expected commissions: each send of 1.00 incurs 0.015 -> rounds to 0.02? Actually 1.00 * 1.5% = 0.015 => HALF_UP -> 0.02
    $commissionPerTransfer = '0.02';
    $commissionCents = 2; // 0.02
    $amountCents = 100;
    $debitPerTransferCents = $amountCents + $commissionCents; // 102 cents

    $aSends = (int) ceil($iterations / 2);
    $bSends = (int) floor($iterations / 2);

    // Net effect on A: -aSends*102 + bSends*100
    $aBalanceCents = 1000 * 100 - ($aSends * $debitPerTransferCents) + ($bSends * $amountCents);
    $bBalanceCents = 1000 * 100 - ($bSends * $debitPerTransferCents) + ($aSends * $amountCents);

    $format = fn (int $c) => sprintf('%d.%02d', intdiv($c, 100), $c % 100);

    expect($a->balance)->toBe($format($aBalanceCents));
    expect($b->balance)->toBe($format($bBalanceCents));

    // Ensure total system balance decreased only by total commissions collected.
    $systemStartCents = 2000 * 100;
    $systemEndCents = $aBalanceCents + $bBalanceCents;
    $expectedCommissionCents = ($aSends + $bSends) * $commissionCents;
    expect($systemStartCents - $systemEndCents)->toBe($expectedCommissionCents);
});
