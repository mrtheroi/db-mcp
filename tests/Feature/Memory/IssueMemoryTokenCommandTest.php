<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it issues an MCP token for a user by email', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);

    $this->artisan('memory:token', ['email' => 'me@example.com'])
        ->expectsOutputToContain('Token:')
        ->assertSuccessful();

    expect($user->tokens()->count())->toBe(1);
});

test('it creates the user after confirmation when the email does not exist', function () {
    $this->artisan('memory:token', ['email' => 'new@example.com'])
        ->expectsConfirmation('User new@example.com does not exist. Create it?', 'yes')
        ->expectsOutputToContain('Token:')
        ->assertSuccessful();

    expect(User::where('email', 'new@example.com')->first()->tokens()->count())->toBe(1);
});

test('it creates nothing when the user creation is declined', function () {
    $this->artisan('memory:token', ['email' => 'typo@example.com'])
        ->expectsConfirmation('User typo@example.com does not exist. Create it?', 'no')
        ->expectsOutputToContain('No token issued.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});
