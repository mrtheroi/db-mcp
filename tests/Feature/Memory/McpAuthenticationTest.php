<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it rejects MCP requests without a token', function () {
    $this->postJson('/mcp/memory', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});

test('it accepts MCP requests with a valid token', function () {
    $token = User::factory()->create()->createToken('claude-code')->plainTextToken;

    $this->withToken($token)
        ->postJson('/mcp/memory', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertOk()
        ->assertJsonPath('result.tools.0.name', 'save-memory');
});

test('it limits each user to 60 MCP requests per minute', function () {
    $token = User::factory()->create()->createToken('claude-code')->plainTextToken;
    $listTools = fn () => $this->withToken($token)->postJson('/mcp/memory', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    foreach (range(1, 60) as $request) {
        $listTools()->assertOk();
    }

    $listTools()->assertTooManyRequests();
});

test('it counts the rate limit per user', function () {
    $listTools = fn (string $token) => $this->withToken($token)->postJson('/mcp/memory', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);
    $busy = User::factory()->create()->createToken('claude-code')->plainTextToken;
    $quiet = User::factory()->create()->createToken('claude-code')->plainTextToken;

    foreach (range(1, 60) as $request) {
        $listTools($busy);
    }

    $listTools($busy)->assertTooManyRequests();

    $this->app['auth']->forgetGuards();
    $listTools($quiet)->assertOk();
});
