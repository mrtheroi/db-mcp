<?php

use App\Mcp\Servers\MemoryServer;
use App\Mcp\Tools\SessionSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it saves the session summary as an observation', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SessionSummary::class, [
            'session_id' => 'session-1',
            'project' => 'dbmcp',
            'content' => "## Goal\nBuild the search tool\n\n## Next Steps\n- Phase 4",
        ])
        ->assertOk()
        ->assertSee('Session summary saved');

    $this->assertDatabaseHas('observations', [
        'user_id' => $user->id,
        'session_id' => 'session-1',
        'type' => 'session_summary',
        'title' => 'Session summary: dbmcp',
        'project' => 'dbmcp',
    ]);
});

test('it names the repo in the title when one is given, keeping it as written after trimming', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SessionSummary::class, [
            'session_id' => 'session-1',
            'project' => 'memry',
            'repo' => '  memry-CLI ',
            'content' => '## Goal',
        ])
        ->assertOk();

    $this->assertDatabaseHas('observations', [
        'title' => 'Session summary: memry (memry-CLI)',
        'project' => 'memry',
    ]);
});

test('it rejects a repo that is not a string', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SessionSummary::class, [
            'session_id' => 'session-1',
            'project' => 'memry',
            'repo' => ['memry-cli'],
            'content' => '## Goal',
        ])
        ->assertHasErrors(['The repo field must be a string.']);

    $this->assertDatabaseCount('observations', 0);
});

test('it keeps the plain title when the repo is blank', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SessionSummary::class, [
            'session_id' => 'session-1',
            'project' => 'memry',
            'repo' => '   ',
            'content' => '## Goal',
        ])
        ->assertOk();

    $this->assertDatabaseHas('observations', ['title' => 'Session summary: memry']);
});

test('it rejects a session summary without a required field', function (string $field, string $message) {
    $user = User::factory()->create();

    $arguments = [
        'session_id' => 'session-1',
        'project' => 'dbmcp',
        'content' => '## Goal',
    ];
    unset($arguments[$field]);

    MemoryServer::actingAs($user)
        ->tool(SessionSummary::class, $arguments)
        ->assertHasErrors([$message]);

    $this->assertDatabaseCount('observations', 0);
})->with([
    'session_id' => ['session_id', 'The session id field is required.'],
    'project' => ['project', 'The project field is required.'],
    'content' => ['content', 'The content field is required.'],
]);

test('it rejects a session summary with a field that is too long', function (string $field, int $length, string $message) {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SessionSummary::class, [
            'session_id' => 'session-1',
            'project' => 'dbmcp',
            'content' => '## Goal',
            $field => str_repeat('a', $length),
        ])
        ->assertHasErrors([$message]);

    $this->assertDatabaseCount('observations', 0);
})->with([
    'session_id' => ['session_id', 256, 'The session id field must not be greater than 255 characters.'],
    'project' => ['project', 256, 'The project field must not be greater than 255 characters.'],
    'content' => ['content', 20001, 'The content field must not be greater than 20000 characters.'],
    'repo' => ['repo', 256, 'The repo field must not be greater than 255 characters.'],
]);

test('it accepts a project name at the maximum length', function () {
    $user = User::factory()->create();

    MemoryServer::actingAs($user)
        ->tool(SessionSummary::class, [
            'session_id' => 'session-1',
            'project' => str_repeat('a', 255),
            'content' => '## Goal',
        ])
        ->assertOk();

    $this->assertDatabaseCount('observations', 1);
});
