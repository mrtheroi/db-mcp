<?php

use App\Mcp\Servers\MemoryServer;
use App\Mcp\Tools\SearchMemory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it finds only the authenticated user memories that match the query', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    remember($user, 'Save Sanctum tokens hashed', 'Never store plain tokens');
    remember($user, 'Deploy to Laravel Cloud', 'Serverless Postgres with hibernation');
    remember($stranger, 'Stranger saves tokens too', 'Must never leak');

    MemoryServer::actingAs($user)
        ->tool(SearchMemory::class, ['query' => 'saving token'])
        ->assertOk()
        ->assertSee('Save Sanctum tokens hashed')
        ->assertDontSee('Deploy to Laravel Cloud')
        ->assertDontSee('Stranger saves tokens too');
});

test('it rejects a limit outside 1 to 20', function (int $limit, string $message) {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SearchMemory::class, ['query' => 'tokens', 'limit' => $limit])
        ->assertHasErrors([$message]);
})->with([
    'negative' => [-1, 'The limit field must be at least 1.'],
    'zero' => [0, 'The limit field must be at least 1.'],
    'too many' => [21, 'The limit field must not be greater than 20.'],
]);

test('it tells the agent when no memories match', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SearchMemory::class, ['query' => 'kubernetes'])
        ->assertOk()
        ->assertSee('No memories found.');
});

test('it rejects a query that is too long', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SearchMemory::class, ['query' => str_repeat('a', 256)])
        ->assertHasErrors(['The query field must not be greater than 255 characters.']);
});
