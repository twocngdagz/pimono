<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Base demo users always created in every environment
        $alice = User::factory()->create([
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => bcrypt('password1'),
            'balance' => 1000.00,
        ]);
        $bob = User::factory()->create([
            'name' => 'Bob Example',
            'email' => 'bob@example.com',
            'password' => bcrypt('password2'),
            'balance' => 500.00,
        ]);
        User::factory()->create([
            'name' => 'Charlie Example',
            'email' => 'charlie@example.com',
            'password' => bcrypt('password3'),
            'balance' => 50.00,
        ]);

        // Extended demo dataset only for non-production, non-test and when flag enabled
        $demoEnabled = filter_var(env('DEMO_SEED', true), FILTER_VALIDATE_BOOL);
        if ($demoEnabled && ! app()->runningUnitTests() && ! app()->environment('production')) {
            $this->call(DemoDataSeeder::class);
        }

        // Optional very large dataset seeding (heavy) - behind separate flag
        $largeEnabled = filter_var(env('DEMO_SEED_MAX', false), FILTER_VALIDATE_BOOL);
        if ($largeEnabled && ! app()->runningUnitTests() && ! app()->environment('production')) {
            $this->call(LargeDemoDataSeeder::class);
        }
    }
}
