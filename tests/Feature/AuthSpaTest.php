<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\getJson; // non-JSON for session (form) but we'll send JSON anyway
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

it('logs in with valid credentials and can access /api/user', function () {
    $user = User::factory()->create([
        'email' => 'authuser@example.com',
        'password' => Hash::make('topsecret'),
    ]);

    // Sanctum CSRF cookie endpoint (simulate browser)
    $csrf = getJson('/sanctum/csrf-cookie');
    $csrf->assertStatus(204); // cookie set (some setups 204 / 200 both acceptable)

    $login = postJson('/login', [
        'email' => 'authuser@example.com',
        'password' => 'topsecret',
    ]);

    $login->assertOk()->assertJsonPath('user.email', 'authuser@example.com');

    $me = getJson('/api/user');
    $me->assertOk()->assertJsonPath('email', 'authuser@example.com');
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'badlogin@example.com',
        'password' => Hash::make('correct'),
    ]);

    getJson('/sanctum/csrf-cookie');

    $resp = postJson('/login', [
        'email' => 'badlogin@example.com',
        'password' => 'wrong',
    ]);

    $resp->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('logs out and session can no longer access /api/user', function () {
    $user = User::factory()->create([
        'email' => 'logout@example.com',
        'password' => Hash::make('logmeout'),
    ]);

    getJson('/sanctum/csrf-cookie');
    postJson('/login', [
        'email' => 'logout@example.com',
        'password' => 'logmeout',
    ])->assertOk();

    getJson('/api/user')->assertOk();

    post('/logout')->assertNoContent();

    // Reset application kernel & container to avoid cached guard user state
    $this->refreshApplication();

    getJson('/api/user')->assertStatus(401);
});
