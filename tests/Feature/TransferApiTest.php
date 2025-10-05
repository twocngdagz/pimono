<?php

use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/**
 * API tests for the /api/transfer endpoint covering validation and domain exceptions.
 */
it('returns 201 and transaction data on successful transfer', function () {
    $sender = User::factory()->create(['balance' => '50.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);

    actingAs($sender, 'sanctum');

    $response = postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => '10.00',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id', 'uuid', 'sender_id', 'receiver_id', 'amount', 'commission_fee', 'status', 'created_at',
            ],
        ]);

    $sender->refresh();
    $receiver->refresh();

    expect($sender->balance)->toBe('39.85')->and($receiver->balance)->toBe('10.00');
});

it('validates receiver_id is required', function () {
    $sender = User::factory()->create(['balance' => '10.00']);
    actingAs($sender, 'sanctum');

    $response = postJson('/api/transactions', [
        // 'receiver_id' missing
        'amount' => '1.00',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['receiver_id']);
});

it('validates receiver must differ from sender', function () {
    $sender = User::factory()->create(['balance' => '10.00']);
    actingAs($sender, 'sanctum');

    $response = postJson('/api/transactions', [
        'receiver_id' => $sender->id,
        'amount' => '1.00',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['receiver_id']);
    expect($response->json('errors.receiver_id.0'))->toBe('Receiver must be different from sender.');
});

it('validates amount format (too many decimals)', function () {
    $sender = User::factory()->create(['balance' => '10.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);
    actingAs($sender, 'sanctum');

    $response = postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => '1.234',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('validates amount minimum (zero)', function () {
    $sender = User::factory()->create(['balance' => '10.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);
    actingAs($sender, 'sanctum');

    $response = postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => '0.00',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
    expect($response->json('errors.amount.0'))->toBe('Amount must be at least 0.01.');
});

it('returns 422 with domain error on insufficient funds', function () {
    $sender = User::factory()->create(['balance' => '1.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);
    actingAs($sender, 'sanctum');

    $response = postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => '1.00', // requires 1.02 including commission
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'type' => 'InsufficientFunds',
        ]);
});

it('returns 409 on idempotency conflict with different parameters', function () {
    $sender = User::factory()->create(['balance' => '30.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);
    actingAs($sender, 'sanctum');

    $key = (string) Str::uuid();

    // First successful transfer
    postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => '5.00',
        'idempotency_key' => $key,
    ])->assertCreated();

    // Second attempt with different amount -> conflict
    $response = postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => '6.00',
        'idempotency_key' => $key,
    ]);

    $response->assertStatus(409)
        ->assertJson([
            'type' => 'IdempotencyConflict',
            'code' => 'wallet.idempotency_conflict',
        ]);
});

it('validates negative amount as invalid format', function () {
    $sender = User::factory()->create(['balance' => '10.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);
    actingAs($sender, 'sanctum');

    $response = postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => '-5.00',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});
