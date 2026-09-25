<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetContext;
use App\Mcp\Tools\GetMemory;
use App\Mcp\Tools\SaveMemory;
use App\Mcp\Tools\SavePrompt;
use App\Mcp\Tools\SearchMemory;
use App\Mcp\Tools\SessionSummary;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Private Save Memory')]
#[Version('0.0.1')]
#[Instructions(<<<'MARKDOWN'
    Persistent memory that survives across sessions and projects for a single user.

    Use it to:
    - Recall prior work before starting a task that may have been done before.
    - Save decisions, bug fixes (with root cause), non-obvious discoveries and conventions right after they happen.

    Rules:
    - Search before saving to avoid duplicates; update an existing memory when the topic is the same.
    - Keep titles short and searchable (verb + what).
    - Never store secrets, credentials or tokens.
    MARKDOWN)]
class MemoryServer extends Server
{
    protected array $tools = [
        SaveMemory::class,
        SearchMemory::class,
        SessionSummary::class,
        GetContext::class,
        SavePrompt::class,
        GetMemory::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
