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
