<?php

use App\Mcp\Servers\MemoryServer;
use App\Mcp\Tools\SaveMemory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it saves an observation for the authenticated user', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SaveMemory::class, [
            'session_id' => 'session-1',
            'type' => 'decision',
            'title' => 'Use Postgres full-text search',
            'content' => 'tsvector + GIN index',
            'project' => 'dbmcp',
        ])
        ->assertOk()
        ->assertSee('Memory saved');

    $this->assertDatabaseHas('observations', [
        'user_id' => $user->id,
        'title' => 'Use Postgres full-text search',
        'scope' => 'project',
    ]);
});

test('it updates the existing memory when the topic key is repeated', function () {
    $user = User::factory()->create();
    $memory = [
        'session_id' => 'session-1',
        'type' => 'architecture',
        'title' => 'Auth model',
        'content' => 'Sessions with cookies',
        'project' => 'dbmcp',
        'topic_key' => 'architecture/auth-model',
    ];

    MemoryServer::actingAs($user)->tool(SaveMemory::class, $memory)->assertOk();
    MemoryServer::actingAs($user)
        ->tool(SaveMemory::class, [...$memory, 'content' => 'Sanctum bearer tokens'])
        ->assertOk();

    $this->assertDatabaseCount('observations', 1);
    $this->assertDatabaseHas('observations', [
        'topic_key' => 'architecture/auth-model',
        'content' => 'Sanctum bearer tokens',
    ]);
});

test('it keeps memories of different users separate even with the same topic key', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $memory = [
        'session_id' => 'session-1',
        'type' => 'architecture',
        'title' => 'Auth model',
        'content' => 'Alice uses sessions',
        'project' => 'dbmcp',
        'topic_key' => 'architecture/auth-model',
    ];

    MemoryServer::actingAs($alice)->tool(SaveMemory::class, $memory)->assertOk();
    MemoryServer::actingAs($bob)
        ->tool(SaveMemory::class, [...$memory, 'content' => 'Bob uses tokens'])
        ->assertOk();

    $this->assertDatabaseCount('observations', 2);
    $this->assertDatabaseHas('observations', ['user_id' => $alice->id, 'content' => 'Alice uses sessions']);
    $this->assertDatabaseHas('observations', ['user_id' => $bob->id, 'content' => 'Bob uses tokens']);
});

test('it rejects a memory without a required field', function (string $field, string $message) {
    $user = User::factory()->create();

    $arguments = [
        'session_id' => 'session-1',
        'type' => 'decision',
        'title' => 'Use Postgres full-text search',
        'content' => 'tsvector + GIN index',
    ];
    unset($arguments[$field]);

    MemoryServer::actingAs($user)
        ->tool(SaveMemory::class, $arguments)
        ->assertHasErrors([$message]);

    $this->assertDatabaseCount('observations', 0);
})->with([
    'title' => ['title', 'The title field is required.'],
    'session_id' => ['session_id', 'The session id field is required.'],
    'type' => ['type', 'The type field is required.'],
    'content' => ['content', 'The content field is required.'],
]);

test('it rejects a memory with a field that is too long', function (string $field, int $length, string $message) {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SaveMemory::class, [
            'session_id' => 'session-1',
            'type' => 'decision',
            'title' => 'Use Postgres full-text search',
            'content' => 'tsvector + GIN index',
            'project' => 'dbmcp',
            'topic_key' => 'architecture/search',
            $field => str_repeat('a', $length),
        ])
        ->assertHasErrors([$message]);

    $this->assertDatabaseCount('observations', 0);
})->with([
    'session_id' => ['session_id', 256, 'The session id field must not be greater than 255 characters.'],
    'type' => ['type', 256, 'The type field must not be greater than 255 characters.'],
    'title' => ['title', 256, 'The title field must not be greater than 255 characters.'],
    'project' => ['project', 256, 'The project field must not be greater than 255 characters.'],
    'topic_key' => ['topic_key', 256, 'The topic key field must not be greater than 255 characters.'],
    'content' => ['content', 20001, 'The content field must not be greater than 20000 characters.'],
]);
