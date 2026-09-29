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

test('it finds the context of a project written with a different spelling', function () {
    $user = User::factory()->create();

    remember($user, 'Use Postgres full-text search', 'tsvector + GIN');

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => ' DbMcp '])
        ->assertOk()
        ->assertSee('Use Postgres full-text search');
});

test('it shows the newest session summary in full under recent sessions', function () {
    $user = User::factory()->create();

    remember($user, 'Session one', 'Older summary content', type: 'session_summary');
    $this->travel(1)->minutes();
    $latest = remember($user, 'Session two', "Latest summary content\nwith a second line", type: 'session_summary');

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => 'dbmcp'])
        ->assertOk()
        ->assertSee("## Recent sessions\n#{$latest->id} [session_summary] Session two\nLatest summary content\nwith a second line")
        ->assertDontSee('## Latest session');
});

test('it lists the two previous session summaries with their date and a preview, newest first, dropping older ones', function () {
    $user = User::factory()->create();

    $this->travelTo('2026-09-28 10:00:00');
    remember($user, 'Session summary: memry (dbMcp)', 'Oldest summary content', project: 'memry', type: 'session_summary');
    $this->travelTo('2026-09-28 11:00:00');
    $third = remember($user, 'Session summary: memry (memry-cli)', "Third line one\nline two ".str_repeat('é', 300), project: 'memry', type: 'session_summary');
    $this->travelTo('2026-09-28 12:30:00');
    $second = remember($user, 'Session summary: memry (dbMcp)', 'Second summary content', project: 'memry', type: 'session_summary');
    $this->travelTo('2026-09-28 13:00:00');
    $newest = remember($user, 'Session summary: memry (memry-cli)', 'Newest summary content', project: 'memry', type: 'session_summary');

    $preview = mb_substr('Third line one line two '.str_repeat('é', 300), 0, 300);

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => 'memry'])
        ->assertOk()
        ->assertSee(
            "## Recent sessions\n#{$newest->id} [session_summary] Session summary: memry (memry-cli)\nNewest summary content\n\n"
            ."- #{$second->id} [session_summary] Session summary: memry (dbMcp) (2026-09-28 12:30): Second summary content\n"
            ."- #{$third->id} [session_summary] Session summary: memry (memry-cli) (2026-09-28 11:00): {$preview}…\n\n"
        )
        ->assertDontSee('Oldest summary content');
});

test('it lists topic-key memories under project knowledge with a preview of their content', function () {
    $user = User::factory()->create();

    $long = remember($user, 'Auth model', "Line one\nline two ".str_repeat('é', 300), topicKey: 'architecture/auth');
    $short = remember($user, 'Search engine', 'Postgres full-text search', topicKey: 'architecture/search');

    $preview = mb_substr('Line one line two '.str_repeat('é', 300), 0, 300);

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => 'dbmcp'])
        ->assertOk()
        ->assertSee("## Project knowledge\n")
        ->assertSee("- #{$long->id} [decision] Auth model: {$preview}…")
        ->assertSee("- #{$short->id} [decision] Search engine: Postgres full-text search")
        ->assertDontSee('Postgres full-text search…');
});

test('it lists memories without a topic key under recent memories, title only', function () {
    $user = User::factory()->create();

    $memory = remember($user, 'Fixed flaky token test', 'Root cause: clock drift in the fixture');

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => 'dbmcp'])
        ->assertOk()
        ->assertSee("## Recent memories\n- #{$memory->id} [decision] Fixed flaky token test")
        ->assertDontSee('Root cause: clock drift in the fixture');
});

test('it caps project knowledge at 20 and recent memories at 10, dropping the oldest', function () {
    $user = User::factory()->create();

    foreach (range(1, 21) as $n) {
        remember($user, sprintf('Knowledge %02d', $n), 'Some content', topicKey: "topic/{$n}");
        $this->travel(1)->minutes();
    }

    foreach (range(1, 11) as $n) {
        remember($user, sprintf('Recent %02d', $n), 'Some content');
        $this->travel(1)->minutes();
    }

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => 'dbmcp'])
        ->assertOk()
        ->assertSee(['Knowledge 21', 'Knowledge 02', 'Recent 11', 'Recent 02'])
        ->assertDontSee('Knowledge 01')
        ->assertDontSee('Recent 01');
});

test('it omits empty sections and ends with a hint to read memories in full', function () {
    $user = User::factory()->create();

    $memory = remember($user, 'Fixed flaky token test', 'Root cause: clock drift');

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => 'dbmcp'])
        ->assertOk()
        ->assertSee("## Recent memories\n- #{$memory->id} [decision] Fixed flaky token test\n\nUse get-memory with an id to read a memory in full.")
        ->assertDontSee('## Recent sessions')
        ->assertDontSee('## Project knowledge');
});

test('it never shows session summaries or project knowledge of another user or project', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    remember($user, 'Own summary', 'Own session', type: 'session_summary');
    remember($user, 'Own knowledge', 'Own topic', topicKey: 'architecture/own');
    $this->travel(1)->minutes();
    remember($stranger, 'Stranger summary', 'Must never leak', type: 'session_summary');
    remember($stranger, 'Stranger knowledge', 'Must never leak', topicKey: 'architecture/own');
    remember($user, 'Other project summary', 'Wrong project', project: 'other-app', type: 'session_summary');
    remember($user, 'Other project knowledge', 'Wrong project', project: 'other-app', topicKey: 'architecture/own');

    MemoryServer::actingAs($user)
        ->tool(GetContext::class, ['project' => 'dbmcp'])
        ->assertOk()
        ->assertSee(['Own summary', 'Own knowledge'])
        ->assertDontSee('Stranger')
        ->assertDontSee('Other project');
});
