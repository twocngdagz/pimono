<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

it('returns balance and recent transactions with direction', function () {
    $user = User::factory()->create(['balance' => '100.00']);
    $other = User::factory()->create(['balance' => '50.00']);

    // Create inbound transaction (other -> user)
    $inbound = Transaction::create([
        'uuid' => (string) Str::uuid(),
        'sender_id' => $other->id,
        'receiver_id' => $user->id,
        'amount' => '10.00',
        'commission_fee' => '0.15',
        'status' => 'success',
    ]);

    // Create outbound transaction (user -> other)
    $outbound = Transaction::create([
        'uuid' => (string) Str::uuid(),
        'sender_id' => $user->id,
        'receiver_id' => $other->id,
        'amount' => '5.00',
        'commission_fee' => '0.08',
        'status' => 'success',
    ]);

    actingAs($user, 'sanctum');

    $res = getJson('/api/transactions');

    $res->assertOk()
        ->assertJsonStructure([
            'data' => [
                'balance',
                'transactions' => [
                    ['id', 'uuid', 'sender_id', 'receiver_id', 'amount', 'commission_fee', 'status', 'direction', 'created_at'],
                ],
            ],
        ]);

    $transactions = collect($res->json('data.transactions'));
    expect($transactions->pluck('id'))->toContain($inbound->id, $outbound->id);

    $inPayload = $transactions->firstWhere('id', $inbound->id);
    $outPayload = $transactions->firstWhere('id', $outbound->id);
    expect($inPayload['direction'])->toBe('in');
    expect($outPayload['direction'])->toBe('out');
});
