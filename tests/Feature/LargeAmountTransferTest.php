<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns InsufficientFunds for unrepresentable amount when balance is insufficient', function () {
    $sender = User::factory()->create();
    $sender->balance = '1000.00';
    $sender->save();
    $receiver = User::factory()->create();

    Sanctum::actingAs($sender);

    $response = $this->postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => '999999999999999999.00',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'wallet.insufficient_funds');
});

it('returns AmountTooLarge for unrepresentable amount when balance is sufficient', function () {
    $sender = User::factory()->create();
    $sender->balance = '999999999999999999.00';
    $sender->save();
    $receiver = User::factory()->create();

    Sanctum::actingAs($sender);

    $response = $this->postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => '999999999999999999.00',
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('code', 'wallet.amount_too_large');
});

it('returns AmountTooLarge when amount is representable but commission pushes total over limit', function () {

    $amount = '92233720368547757.00';
    $sender = User::factory()->create();
    $sender->balance = $amount;
    $sender->save();
    $receiver = User::factory()->create();

    Sanctum::actingAs($sender);

    $response = $this->postJson('/api/transactions', [
        'receiver_id' => $receiver->id,
        'amount' => $amount,
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('code', 'wallet.amount_too_large');
});
