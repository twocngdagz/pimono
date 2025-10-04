<?php

use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\Exceptions\InsufficientFunds;
use App\Services\Wallet\TransferService;
use Illuminate\Support\Str; // import for idempotency key

it('transfers funds with commission and records transaction', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    // Seed balances
    $sender->balance = '100.00';
    $sender->save();
    $receiver->balance = '5.00';
    $receiver->save();

    /** @var TransferService $service */
    $service = app(TransferService::class);

    $tx = $service->transfer($sender, $receiver->id, '10'); // amount without decimals to test normalization

    $sender->refresh();
    $receiver->refresh();

    // Commission 1.5% of 10.00 = 0.15 (HALF_UP)
    expect($tx)->toBeInstanceOf(Transaction::class)
        ->and($tx->amount)->toBe('10.00')
        ->and($tx->commission_fee)->toBe('0.15')
        ->and($tx->status)->toBe('success');

    // Sender debited 10.15, receiver credited 10.00
    expect($sender->balance)->toBe('89.85');
    expect($receiver->balance)->toBe('15.00');

    expect(Transaction::count())->toBe(1);
});

it('throws InsufficientFunds and leaves balances unchanged', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $sender->balance = '5.00';
    $sender->save();
    $receiver->balance = '0.00';
    $receiver->save();

    /** @var TransferService $service */
    $service = app(TransferService::class);

    expect(fn () => $service->transfer($sender, $receiver->id, '10.00'))
        ->toThrow(InsufficientFunds::class);

    $sender->refresh();
    $receiver->refresh();

    expect($sender->balance)->toBe('5.00');
    expect($receiver->balance)->toBe('0.00');
    expect(Transaction::count())->toBe(0);
});

it('is idempotent when idempotency key provided', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $sender->balance = '50.00';
    $sender->save();
    $receiver->balance = '0.00';
    $receiver->save();

    $key = (string) Str::uuid();

    /** @var TransferService $service */
    $service = app(TransferService::class);

    $first = $service->transfer($sender, $receiver->id, '20.00', $key);
    $second = $service->transfer($sender->fresh(), $receiver->id, '20.00', $key); // simulate retry

    $sender->refresh();
    $receiver->refresh();

    // Only one transaction persisted
    expect(Transaction::count())->toBe(1);
    expect($first->id)->toBe($second->id);

    // Commission 1.5% of 20.00 = 0.30 => total debit 20.30
    expect($sender->balance)->toBe('29.70');
    expect($receiver->balance)->toBe('20.00');
});
