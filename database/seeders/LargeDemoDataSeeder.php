<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Generates a large volume dataset useful for performance / pagination / reporting demos.
 * Triggered only when DEMO_SEED_MAX=true and never in production or during tests.
 */
class LargeDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $usersToCreate = max(0, (int) env('DEMO_USERS_MAX', 250));
        $totalTransfers = max(0, (int) env('DEMO_TRANSFERS_MAX', 7500));
        $failedSamples = max(0, (int) env('DEMO_FAILED_MAX', 150));
        $bigAmounts = array_filter(array_map('trim', explode(',', (string) env('DEMO_BIG_TRANSFERS_MAX', '50000.00,125000.00,250000.00,500000.00'))));

        // Whale user
        $whale = User::factory()->create([
            'name' => 'Mega Whale',
            'email' => 'mega.whale@example.com',
            'password' => bcrypt('password'),
        ]);
        $whale->balance = '5000000.00';
        $whale->save();

        $demoUsers = collect();
        for ($i = 1; $i <= $usersToCreate; $i++) {
            $u = User::factory()->create([
                'name' => 'Load User '.$i,
                'email' => "load{$i}@example.com",
                'password' => bcrypt('password'),
            ]);
            $u->balance = number_format(random_int(500, 7500000) / 100, 2, '.', ''); // 5.00 - 75,000.00
            $u->save();
            $demoUsers->push($u);
        }

        /** @var TransferService $service */
        $service = app(TransferService::class);
        $all = $demoUsers->push($whale)->all();

        for ($t = 0; $t < $totalTransfers; $t++) {
            if (count($all) < 2) {
                break;
            }
            $sender = $all[array_rand($all)];
            $receiver = $all[array_rand($all)];
            if ($sender->id === $receiver->id) {
                continue;
            }
            $amount = number_format(random_int(100, 250000) / 100, 2, '.', ''); // 1.00 - 2,500.00
            try {
                $service->transfer($sender->fresh(), $receiver->id, $amount);
            } catch (\Throwable) {
                // ignore
            }
        }

        foreach ($bigAmounts as $bigAmount) {
            if ($demoUsers->isEmpty()) {
                break;
            }
            $target = $demoUsers->random();
            try {
                $service->transfer($whale->fresh(), $target->id, $bigAmount);
            } catch (\Throwable) {
                // ignore
            }
        }

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
                $rawCents = random_int(100, 400000); // 1.00 - 4,000.00
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
