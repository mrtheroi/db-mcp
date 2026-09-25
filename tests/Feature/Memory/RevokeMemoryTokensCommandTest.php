<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it revokes every token of a user by email', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);
    $user->createToken('laptop');
    $user->createToken('desktop');

    $this->artisan('memory:revoke', ['email' => 'me@example.com'])
        ->expectsOutputToContain('Revoked 2 tokens')
        ->assertSuccessful();

    expect($user->tokens()->count())->toBe(0);
});

test('it fails clearly when the user does not exist', function () {
    $this->artisan('memory:revoke', ['email' => 'ghost@example.com'])
        ->expectsOutputToContain('User ghost@example.com not found.')
        ->assertFailed();
});

test('it keeps the tokens of other users', function () {
    User::factory()->create(['email' => 'me@example.com'])->createToken('laptop');
    $other = User::factory()->create();
    $other->createToken('laptop');

    $this->artisan('memory:revoke', ['email' => 'me@example.com'])->assertSuccessful();

    expect($other->tokens()->count())->toBe(1);
});
