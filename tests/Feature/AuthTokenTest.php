<?php

use App\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('issues a personal access token and allows bearer auth on protected route', function () {
    $user = User::factory()->create([
        'email' => 'alice.token@example.com',
        'password' => bcrypt('secretpass'),
        'balance' => '123.45',
    ]);

    $res = postJson('/api/token', [
        'email' => $user->email,
        'password' => 'secretpass',
        'device_name' => 'test-suite',
    ]);

    $res->assertOk()->assertJsonStructure([
        'token_type',
        'token',
        'user' => ['id', 'email', 'name', 'balance'],
    ]);

    $token = $res->json('token');
    expect($token)->not->toBeEmpty();

    // Call protected route using Bearer token
    $txRes = getJson('/api/transactions', [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ]);

    $txRes->assertOk()->assertJsonStructure([
        'data' => [
            'balance',
            'transactions',
        ],
    ]);
});

it('rejects invalid credentials when requesting a token', function () {
    $user = User::factory()->create([
        'email' => 'bob.token@example.com',
        'password' => bcrypt('correctpass'),
    ]);

    $res = postJson('/api/token', [
        'email' => $user->email,
        'password' => 'wrongpass',
    ]);

    $res->assertStatus(422)->assertJsonPath('errors.email.0', 'Invalid credentials.');
});
