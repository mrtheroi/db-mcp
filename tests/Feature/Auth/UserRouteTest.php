<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the scaffold /api/user route is not exposed', function () {
    $token = User::factory()->create()->createToken('memry-cli')->plainTextToken;

    $this->withToken($token)->getJson('/api/user')->assertNotFound();
});
