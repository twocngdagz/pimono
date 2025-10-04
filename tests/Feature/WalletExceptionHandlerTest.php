<?php

use App\Services\Wallet\Exceptions\AmountMustBeGreaterThanZero;
use App\Services\Wallet\Exceptions\CannotTransferToSelf;
use App\Services\Wallet\Exceptions\IdempotencyConflict;
use App\Services\Wallet\Exceptions\InsufficientFunds;
use App\Services\Wallet\Exceptions\InvalidAmountFormat;
use App\Services\Wallet\Exceptions\ReceiverNotFound;
use App\Services\Wallet\Exceptions\SenderNotFound;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\postJson;

it('maps AmountMustBeGreaterThanZero to 400 JSON response', function () {
      Route::post('/_test/ex/amt-zero', function () {
        throw new AmountMustBeGreaterThanZero('Amount must be greater than zero.');
    });

    $response = postJson('/_test/ex/amt-zero');
    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Amount must be greater than zero.',
            'type' => 'AmountMustBeGreaterThanZero',
        ]);
});

it('maps InvalidAmountFormat to 400 JSON response', function () {
    Route::post('/_test/ex/amt-format', function () {
        throw new InvalidAmountFormat('Invalid amount format.');
    });

    $response = postJson('/_test/ex/amt-format');
    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Invalid amount format.',
            'type' => 'InvalidAmountFormat',
        ]);
});

it('maps CannotTransferToSelf to 422 JSON response', function () {
    Route::post('/_test/ex/self', function () {
        throw new CannotTransferToSelf('Cannot transfer to the same user.');
    });

    $response = postJson('/_test/ex/self');
    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Cannot transfer to the same user.',
            'type' => 'CannotTransferToSelf',
        ]);
});

it('maps InsufficientFunds to 422 JSON response', function () {
    Route::post('/_test/ex/funds', function () {
        throw new InsufficientFunds('Insufficient balance to perform transfer.');
    });

    $response = postJson('/_test/ex/funds');
    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Insufficient balance to perform transfer.',
            'type' => 'InsufficientFunds',
        ]);
});

it('maps ReceiverNotFound to 422 JSON response', function () {
    Route::post('/_test/ex/recv', function () {
        throw new ReceiverNotFound('Receiver not found.');
    });

    $response = postJson('/_test/ex/recv');
    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Receiver not found.',
            'type' => 'ReceiverNotFound',
        ]);
});

it('maps SenderNotFound to 422 JSON response', function () {
    Route::post('/_test/ex/sender', function () {
        throw new SenderNotFound('Sender not found.');
    });

    $response = postJson('/_test/ex/sender');
    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Sender not found.',
            'type' => 'SenderNotFound',
        ]);
});

it('maps IdempotencyConflict to 422 JSON response', function () {
    Route::post('/_test/ex/idempotency', function () {
        throw new IdempotencyConflict('Idempotency key reused with different parameters.');
    });

    $response = postJson('/_test/ex/idempotency');
    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Idempotency key reused with different parameters.',
            'type' => 'IdempotencyConflict',
        ]);
});
