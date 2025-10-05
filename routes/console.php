<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:stats', function () {
    if (app()->environment('production')) {
        $this->error('Refusing to run in production.');

        return Command::FAILURE;
    }
    $users = User::count();
    $tx = Transaction::count();
    $success = Transaction::where('status', 'success')->count();
    $failed = Transaction::where('status', 'failed')->count();
    $this->info("Users: $users");
    $this->info("Transactions: $tx (success=$success failed=$failed)");
    $largest = Transaction::where('status', 'success')->orderByDesc('amount')->limit(5)->get();
    if ($largest->isNotEmpty()) {
        $this->line('Top 5 successful transfers:');
        foreach ($largest as $t) {
            $this->line(sprintf(' - %s fee %s #%d -> #%d', $t->amount, $t->commission_fee, $t->sender_id, $t->receiver_id));
        }
    }
})->purpose('Show demo dataset summary (non-production only)');

Artisan::command('demo:generate {--large : Also seed large volume dataset} {--fresh : Run migrate:fresh before seeding}', function () {
    if (app()->environment('production')) {
        $this->error('Refusing to seed demo data in production.');

        return Command::FAILURE;
    }
    if ($this->option('fresh')) {
        $this->call('migrate:fresh');
    }
    // Base + extended demo
    config(['database.default' => env('DB_CONNECTION', 'sqlite')]);
    $this->call('db:seed', ['--class' => Database\Seeders\DatabaseSeeder::class]);
    if ($this->option('large')) {
        // Force large dataset regardless of flag via direct call
        $this->call('db:seed', ['--class' => Database\Seeders\LargeDemoDataSeeder::class]);
    }
    $this->call('demo:stats');
})->purpose('Generate demo (and optional large) dataset and show stats');
