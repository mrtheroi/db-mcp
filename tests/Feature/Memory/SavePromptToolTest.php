<?php

use App\Mcp\Servers\MemoryServer;
use App\Mcp\Tools\SavePrompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it saves the user prompt for the authenticated user', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SavePrompt::class, [
            'session_id' => 'session-1',
            'project' => 'dbmcp',
            'content' => 'Add full-text search to the memory server',
        ])
        ->assertOk()
        ->assertSee('Prompt saved');

    $this->assertDatabaseHas('user_prompts', [
        'user_id' => $user->id,
        'session_id' => 'session-1',
        'project' => 'dbmcp',
        'content' => 'Add full-text search to the memory server',
    ]);
});

test('it rejects a prompt without a required field', function (string $field, string $message) {
    $user = User::factory()->create();

    $arguments = [
        'session_id' => 'session-1',
        'content' => 'Add full-text search',
    ];
    unset($arguments[$field]);

    MemoryServer::actingAs($user)
        ->tool(SavePrompt::class, $arguments)
        ->assertHasErrors([$message]);

    $this->assertDatabaseCount('user_prompts', 0);
})->with([
    'session_id' => ['session_id', 'The session id field is required.'],
    'content' => ['content', 'The content field is required.'],
]);

test('it rejects a prompt with a field that is too long', function (string $field, int $length, string $message) {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SavePrompt::class, [
            'session_id' => 'session-1',
            'project' => 'dbmcp',
            'content' => 'Add full-text search',
            $field => str_repeat('a', $length),
        ])
        ->assertHasErrors([$message]);

    $this->assertDatabaseCount('user_prompts', 0);
})->with([
    'session_id' => ['session_id', 256, 'The session id field must not be greater than 255 characters.'],
    'project' => ['project', 256, 'The project field must not be greater than 255 characters.'],
    'content' => ['content', 20001, 'The content field must not be greater than 20000 characters.'],
]);
