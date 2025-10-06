<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeUsers(): array
{
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    // Provide sender with sufficient balance for normal test
    $sender->balance = '1000.00';
    $sender->save();
    $receiver->balance = '0.00';
    $receiver->save();

    return [$sender, $receiver];
}

it('rejects amount exceeding integer digit limit (19 digits)', function () {
    [$sender, $receiver] = makeUsers();

    $payload = [
        'receiver_id' => $receiver->id,
        'amount' => '1000000000000000000.00', // 19 digits before decimal
    ];

    $res = $this->actingAs($sender)->postJson('/api/transactions', $payload);

    $res->assertStatus(422)->assertJsonValidationErrors(['amount']);
    expect($res->json('errors.amount.0'))->toContain('Amount format is invalid');
});

it('rejects amount below minimum 0.01', function () {
    [$sender, $receiver] = makeUsers();

    $payload = [
        'receiver_id' => $receiver->id,
        'amount' => '0.00',
    ];

    $res = $this->actingAs($sender)->postJson('/api/transactions', $payload);

    $res->assertStatus(422)->assertJsonValidationErrors(['amount']);
    expect($res->json('errors.amount.0'))->toContain('at least 0.01');
});

it('accepts a normal valid amount and creates a transaction', function () {
    [$sender, $receiver] = makeUsers();

    $payload = [
        'receiver_id' => $receiver->id,
        'amount' => '10.00',
    ];

    $res = $this->actingAs($sender)->postJson('/api/transactions', $payload);

    $res->assertStatus(201);
    $data = $res->json('data');
    expect($data)->not()->toBeNull();
    expect($data['amount'])->toBe('10.00');
    expect($data['commission_fee'])->toBe('0.15'); // 1.5% of 10.00
});
