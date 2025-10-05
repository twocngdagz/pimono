<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $usersToCreate = max(0, (int) env('DEMO_USERS', 20));
        $totalTransfers = max(0, (int) env('DEMO_TRANSFERS', 200));
        $failedSamples = max(0, (int) env('DEMO_FAILED', 5));
        $bigAmounts = array_filter(array_map('trim', explode(',', (string) env('DEMO_BIG_TRANSFERS', '1000.00,25000.00,99999.99'))));

        // Create a high-balance "Whale" user for large transfers
        $whale = User::factory()->create([
            'name' => 'Whale User',
            'email' => 'whale@example.com',
            'password' => bcrypt('password'),
        ]);
        $whale->balance = '1000000.00';
        $whale->save();

        // Bulk create additional demo users
        $demoUsers = collect();
        for ($i = 1; $i <= $usersToCreate; $i++) {
            $u = User::factory()->create([
                'name' => 'Demo User '.$i,
                'email' => "demo{$i}@example.com",
                'password' => bcrypt('password'),
            ]);
            // Random starting balance between 10.00 and 25,000.00
            $u->balance = number_format(random_int(1000, 2500000) / 100, 2, '.', '');
            $u->save();
            $demoUsers->push($u);
        }

        /** @var TransferService $service */
        $service = app(TransferService::class);

        // Perform a series of random successful transfers to build ledger depth
        $all = $demoUsers->push($whale)->all();
        for ($t = 0; $t < $totalTransfers; $t++) {
            if (count($all) < 2) {
                break;
            }
            $sender = $all[array_rand($all)];
            $receiver = $all[array_rand($all)];
            if ($sender->id === $receiver->id) {
                continue; // skip self
            }
            $amount = number_format(random_int(100, 50000) / 100, 2, '.', ''); // 1.00 - 500.00
            try {
                $service->transfer($sender->fresh(), $receiver->id, $amount);
            } catch (\Throwable $e) {
                continue; // ignore domain errors
            }
        }

        // Create configured very large transfers from whale to random users
        foreach ($bigAmounts as $bigAmount) {
            if ($demoUsers->isEmpty()) {
                break;
            }
            $target = $demoUsers->random();
            try {
                $service->transfer($whale->fresh(), $target->id, $bigAmount);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Manually insert FAILED transactions to illustrate status variety
        if ($failedSamples > 0) {
            $userIds = User::pluck('id')->all();
            for ($i = 0; $i < $failedSamples; $i++) {
                if (count($userIds) < 2) {
                    break;
                }
                $senderId = $userIds[array_rand($userIds)];
                $receiverId = $userIds[array_rand($userIds)];
                if ($senderId === $receiverId) {
                    continue;
                }
                $rawCents = random_int(100, 20000); // 1.00 to 200.00
                $amount = number_format($rawCents / 100, 2, '.', '');
                $commissionCents = intdiv(($rawCents * 15) + 500, 1000);
                $commission = number_format($commissionCents / 100, 2, '.', '');
                Transaction::create([
                    'uuid' => (string) Str::uuid(),
                    'sender_id' => $senderId,
                    'receiver_id' => $receiverId,
                    'amount' => $amount,
                    'commission_fee' => $commission,
                    'status' => 'failed',
                ]);
            }
        }
    }
}
