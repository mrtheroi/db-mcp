<?php

use App\Mcp\Servers\MemoryServer;
use App\Mcp\Tools\GetContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it returns the recent memories of the project for the authenticated user', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    remember($user, 'Use Postgres full-text search', 'tsvector + GIN');
    remember($user, 'Pick a CSS framework', 'Tailwind', project: 'other-app');
    remember($stranger, 'Stranger decision', 'Must never leak');

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => 'dbmcp'])
        ->assertOk()
        ->assertSee('Use Postgres full-text search')
        ->assertDontSee('Pick a CSS framework')
        ->assertDontSee('Stranger decision');
});

test('it tells the agent when the project has no context yet', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => 'brand-new'])
        ->assertOk()
        ->assertSee('No context found for project brand-new.');
});

test('it requires a project', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, [])
        ->assertHasErrors(['The project field is required.']);
});

test('it rejects a project name that is too long', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => str_repeat('a', 256)])
        ->assertHasErrors(['The project field must not be greater than 255 characters.']);
});
