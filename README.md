# dbMcp — Private Memory MCP Server

Version **0.3.1** · [Changelog](CHANGELOG.md)

A private, remote memory server for AI agents. Agents save and recall knowledge (decisions, bug fixes, conventions, session summaries) across sessions and projects through MCP tools served over HTTP. Think [Engram](https://github.com/Gentleman-Programming/engram), but hosted and multi-user.

## How it works

```
Agent (Claude Code) ──POST /mcp/memory + Bearer token──▶ auth:sanctum ──▶ MemoryServer
                                                                         │
                                        tools/call ──▶ Tool ──▶ Use case / Port ──▶ Postgres
```

The agent never touches the database: it discovers the tools with `tools/list` and invokes them with `tools/call`.

## Tools

| Tool | Arguments (* required) | Behavior |
| --- | --- | --- |
| `save-memory` | `session_id`*, `type`*, `title`*, `content`*, `project`, `topic_key` | Saves an observation. The same `topic_key` in the same project updates it instead of duplicating it. |
| `search-memory` | `query`*, `limit` (1–20, default 10) | Full-text search (title weighs more than content), ordered by relevance. |
| `session-summary` | `session_id`*, `project`*, `content`* | Saves the session summary as an observation of type `session_summary`. |
| `get-context` | `project`* | Returns the 20 most recently updated memories of the project. |
| `save-prompt` | `session_id`*, `content`*, `project` | Stores the user's prompt verbatim. |

## Stack

- Laravel 13 · PHP 8.4 · [`laravel/mcp`](https://github.com/laravel/mcp) v1
- Laravel Sanctum (bearer tokens)
- Postgres 17 (Laravel Cloud Serverless Postgres in production)
- Pest 5

## Getting started

Requirements: PHP 8.4, Composer, Docker, Node 22.19+ (only for the MCP Inspector).

```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_* in .env to your Postgres, then:
php artisan migrate
php artisan memory:token you@example.com   # prints a token once
php artisan memory:token you@example.com --create   # non-interactive consoles (e.g. Laravel Cloud)
```

Connect Claude Code:

```bash
claude mcp add --transport http memory https://<your-host>/mcp/memory \
  --header "Authorization: Bearer <token>"
```

## Configuration

| Variable | Purpose |
| --- | --- |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Postgres connection |
| `API_VERSION` | Overrides the version in `config/api.php` (optional) |

## Tests

Tests run against a local Postgres 17 container, **never** against the production database (`RefreshDatabase` wipes tables):

```bash
docker run -d --name dbmcp-pg-test -e POSTGRES_USER=dbmcp -e POSTGRES_PASSWORD=secret \
  -e POSTGRES_DB=dbmcp_testing -p 5432:5432 pgvector/pgvector:pg17
./vendor/bin/pest
```

`phpunit.xml` overrides the `DB_*` variables to point at that container.

## Architecture

Screaming + hexagonal: the domain knows nothing about Laravel, Eloquent or MCP; tools are thin input adapters.

```
app/
├── Console/
│   └── Commands/
│       └── IssueMemoryToken.php           # memory:token — issues a Sanctum token, creates the user after confirmation
├── Mcp/
│   ├── Servers/
│   │   └── MemoryServer.php               # Server name, instructions and registered tools
│   └── Tools/
│       ├── GetContext.php                 # Recent memories of a project
│       ├── SaveMemory.php                 # Save an observation (validation + upsert)
│       ├── SavePrompt.php                 # Store the user prompt
│       ├── SearchMemory.php               # Full-text search with ranking and limit
│       └── SessionSummary.php             # Save the session summary
├── Memory/
│   ├── Application/
│   │   └── SaveObservation.php            # Use case: upsert by topic_key
│   ├── Domain/
│   │   ├── MemoryRepository.php           # Port: save, find, findByTopicKey, search, recent
│   │   ├── Observation.php                # Immutable memory entity
│   │   ├── PromptRepository.php           # Port: save prompts
│   │   └── UserPrompt.php                 # Immutable prompt entity
│   └── Infrastructure/
│       └── Persistence/
│           ├── EloquentMemoryRepository.php   # MemoryRepository adapter (Postgres full-text search)
│           ├── EloquentPromptRepository.php   # PromptRepository adapter
│           ├── ObservationRecord.php          # Eloquent model for observations
│           └── UserPromptRecord.php           # Eloquent model for user_prompts
├── Models/
│   └── User.php                           # User with HasApiTokens
└── Providers/
    └── AppServiceProvider.php             # Binds ports to their adapters
config/
└── api.php                                # Server version
routes/
└── ai.php                                 # /mcp/memory route with auth:sanctum
```

## Security

- `/mcp/memory` requires a Sanctum token and returns 401 without one.
- `user_id` comes from the token, never from tool arguments; every query is scoped to it.
- Only the SHA-256 hash of each token is stored.
- Each user is limited to 60 requests per minute; beyond that the server returns 429.
- Every tool validates its input on the server, including maximum lengths (255 characters for identifiers, 20,000 for content).

## Contributing

Strict TDD (red → green → refactor), one behavior per test, conventional commits. Keep `config/api.php`, `CHANGELOG.md` and this README in sync on every functional change.
