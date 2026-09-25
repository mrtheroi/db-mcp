<?php

use App\Mcp\Servers\MemoryServer;
use App\Mcp\Tools\GetMemory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it returns the full content of the user own memory', function () {
    $user = User::factory()->create();
    $memory = remember($user, 'Save Sanctum tokens hashed', 'Never store plain tokens');

    MemoryServer::actingAs($user)
        ->tool(GetMemory::class, ['id' => $memory->id])
        ->assertOk()
        ->assertSee("#{$memory->id} [decision] Save Sanctum tokens hashed\nNever store plain tokens");
});

test('it does not return a memory of another user', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $memory = remember($stranger, 'Stranger secret decision', 'Must never leak');

    MemoryServer::actingAs($user)
        ->tool(GetMemory::class, ['id' => $memory->id])
        ->assertOk()
        ->assertSee('Memory not found.')
        ->assertDontSee('Must never leak');
});

test('it tells the agent when the memory does not exist', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(GetMemory::class, ['id' => 999999])
        ->assertOk()
        ->assertSee('Memory not found.');
});

test('it rejects an invalid id', function (array $arguments, string $message) {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(GetMemory::class, $arguments)
        ->assertHasErrors([$message]);
})->with([
    'missing' => [[], 'The id field is required.'],
    'not an integer' => [['id' => 'abc'], 'The id field must be an integer.'],
    'zero' => [['id' => 0], 'The id field must be at least 1.'],
]);
