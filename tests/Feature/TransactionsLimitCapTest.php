<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

it('caps the transactions limit at 50 and reports has_more', function () {
    $user = User::factory()->create(['balance' => '1000.00']);
    $other = User::factory()->create(['balance' => '1000.00']);

    // Create 150 outbound transactions to exceed the cap.
    for ($i = 0; $i < 150; $i++) {
        Transaction::create([
            'uuid' => (string) Str::uuid(),
            'sender_id' => $user->id,
            'receiver_id' => $other->id,
            'amount' => '1.00',
            'commission_fee' => '0.02',
            'status' => 'success',
        ]);
    }

    actingAs($user, 'sanctum');

    $res = getJson('/api/transactions?limit=500'); // request way above cap

    $res->assertOk();
    $data = $res->json('data.transactions');
    $meta = $res->json('meta');

    expect($meta['limit'])->toBe(50);
    expect(count($data))->toBe(50);
    expect($meta['has_more'])->toBeTrue();

    // Ensure IDs are strictly descending (ordering correctness)
    $ids = array_map(fn ($t) => $t['id'], $data);
    $sorted = $ids;
    rsort($sorted, SORT_NUMERIC);
    expect($ids)->toBe($sorted);
});
