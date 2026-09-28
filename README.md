# dbMcp — Private Memory MCP Server

Version **0.11.0** · [Changelog](CHANGELOG.md)

A private, remote memory server for AI agents. Agents save and recall knowledge (decisions, bug fixes, conventions, session summaries) across sessions and projects through MCP tools served over HTTP. Think [Engram](https://github.com/Gentleman-Programming/engram), but hosted and multi-user.

## How it works

```
Agent (Claude Code) ──POST /mcp/memory + Bearer token──▶ auth:sanctum ──▶ MemoryServer
                                                                         │
                                        tools/call ──▶ Tool ──▶ Use case / Port ──▶ Postgres
```

The agent never touches the database: it discovers the tools with `tools/list` and invokes them with `tools/call`.

## Tools

Project names are normalized on write and on query (trimmed, lowercased, repeated `--`/`__` collapsed), so `dbmcp`, `dbMcp` and ` DbMcp ` refer to the same project.

| Tool | Arguments (* required) | Behavior |
| --- | --- | --- |
| `save-memory` | `session_id`*, `type`*, `title`*, `content`*, `project`, `topic_key` | Saves an observation. The same `topic_key` in the same project (under any spelling) updates it instead of duplicating it. |
| `search-memory` | `query`*, `limit` (1–20, default 10), `project` | Full-text search (title weighs more than content), ordered by relevance. Pass `project` to search only that project; omit it to search all projects. |
| `session-summary` | `session_id`*, `project`*, `content`* | Saves the session summary as an observation of type `session_summary`. |
| `get-context` | `project`* | Returns a bounded context of the project: the latest session summary in full, up to 20 topic-key memories with a 300-character preview, and up to 10 other recent memories by title. |
| `get-memory` | `id`* | Returns the full content of one of the user's own memories. An id that does not exist or belongs to another user returns `Memory not found.` |
| `save-prompt` | `session_id`*, `content`*, `project` | Stores the user's prompt verbatim. |

## HTTP context endpoint

`GET /api/context?project={name}` returns exactly the text of `get-context` as `text/plain`, so shell hooks (e.g. a Claude Code `SessionStart` hook) can load the project context without speaking MCP JSON-RPC. It uses the same Sanctum bearer tokens and the same rate limit (60 requests per minute per user, shared with MCP requests).

```bash
curl -sf -H "Authorization: Bearer <token>" \
  "https://<your-host>/api/context?project=dbmcp"
```

| Status | When |
| --- | --- |
| `200` | The context, or `No context found for project {project}.` |
| `401` | Missing or invalid token |
| `422` | `project` missing, not a string, or longer than 255 characters |
| `429` | Rate limit exceeded |

## Terminal login

The `memry` CLI logs in without a browser: request a one-time code by email, then exchange it for a Sanctum token. Signup is open: an unknown email creates the user on its first successful login.

```bash
curl -s -X POST https://<your-host>/api/auth/code \
  -H "Content-Type: application/json" -d '{"email":"ada@example.com"}'
# 202 {"message":"If the email is valid, a login code has been sent."}

curl -s -X POST https://<your-host>/api/auth/token \
  -H "Content-Type: application/json" -d '{"email":"ada@example.com","code":"042917"}'
# 200 {"token":"1|..."}
```

The email is trimmed and lowercased. The code has 6 digits, expires in 10 minutes, works once, and is replaced by any newer code for the same email. Only its HMAC-SHA256 hash is stored. The token is named `memry-cli` and works for `/mcp/memory` and `/api/context`.

| Endpoint | Status | When |
| --- | --- | --- |
| `POST /api/auth/code` | `202` | Always for a valid email (same answer whether or not the user exists) |
| | `422` | `email` missing, not a string, not an email, or longer than 255 characters |
| | `429` | More than 3 requests per 10 minutes for the email, or 10 per hour from the IP |
| `POST /api/auth/token` | `200` | `{"token": "..."}` |
| | `422` | Validation errors, or `Invalid or expired code.` (wrong, expired, used or superseded code; the 5th wrong attempt burns the code) |
| | `429` | More than 20 requests per minute from the IP |

## Claude Code SessionStart hook

`hooks/claude-code/session-start.sh` loads the project context into every Claude Code session through `GET /api/context`. The project is the basename of the git top-level of the session `cwd` (or of `cwd` itself outside git); the server normalizes it. It prints a short protocol block for the `db-memory` tools (additive to Engram) followed by the context. The token is passed to curl through stdin, so it never appears in the process list. If the config is missing or incomplete, the request fails or it takes longer than 3 seconds, it prints nothing and exits `0`, so it never blocks a session.

1. Create `~/.config/memry/config.json` (or point `MEMRY_CONFIG` to another path) and restrict it to your user:

   ```json
   {"url": "https://<your-host>", "token": "<token>"}
   ```

   ```bash
   chmod 600 ~/.config/memry/config.json
   ```

2. Register the hook in `~/.claude/settings.json` (requires `jq`, `git` and `curl`):

   ```json
   {
     "hooks": {
       "SessionStart": [
         {
           "matcher": "startup|resume|clear|compact",
           "hooks": [
             {
               "type": "command",
               "command": "/path/to/dbMcp/hooks/claude-code/session-start.sh",
               "timeout": 10
             }
           ]
         }
       ]
     }
   }
   ```

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
php artisan memory:revoke you@example.com           # revokes every token of the user
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
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Mail transport used to send login codes |

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
│       ├── IssueMemoryToken.php           # memory:token — issues a Sanctum token, creates the user after confirmation
│       └── RevokeMemoryTokens.php         # memory:revoke — revokes every token of a user
├── Http/
│   └── Controllers/
│       ├── Auth/
│       │   ├── LoginCodeController.php    # POST /api/auth/code — emails a one-time login code
│       │   └── TokenController.php        # POST /api/auth/token — exchanges the code for a memry-cli token
│       └── ContextController.php          # GET /api/context — plain-text project context
├── Mail/
│   └── LoginCodeMail.php                  # Plain-text mail with the login code
├── Mcp/
│   ├── Servers/
│   │   └── MemoryServer.php               # Server name, instructions and registered tools
│   └── Tools/
│       ├── GetContext.php                 # Layered context of a project (via BuildProjectContext)
│       ├── GetMemory.php                  # Full content of one memory by id
│       ├── SaveMemory.php                 # Save an observation (validation + upsert)
│       ├── SavePrompt.php                 # Store the user prompt
│       ├── SearchMemory.php               # Full-text search with ranking and limit
│       └── SessionSummary.php             # Save the session summary
├── Memory/
│   ├── Application/
│   │   ├── BuildProjectContext.php        # Use case: layered project context text (tool + HTTP)
│   │   └── SaveObservation.php            # Use case: upsert by topic_key
│   ├── Domain/
│   │   ├── MemoryRepository.php           # Port: save, find, findByTopicKey, search, latestSessionSummary, withTopicKey, recentWithoutTopicKey
│   │   ├── Observation.php                # Immutable memory entity
│   │   ├── ProjectName.php                # Normalizes project names
│   │   ├── PromptRepository.php           # Port: save prompts
│   │   └── UserPrompt.php                 # Immutable prompt entity
│   └── Infrastructure/
│       └── Persistence/
│           ├── EloquentMemoryRepository.php   # MemoryRepository adapter (Postgres full-text search)
│           ├── EloquentPromptRepository.php   # PromptRepository adapter
│           ├── ObservationRecord.php          # Eloquent model for observations
│           └── UserPromptRecord.php           # Eloquent model for user_prompts
├── Models/
│   ├── LoginCode.php                      # Hashed one-time code: issue, lookup, attempts, consume
│   └── User.php                           # User with HasApiTokens
└── Providers/
    └── AppServiceProvider.php             # Binds ports to their adapters; mcp, auth-code and auth-token rate limiters
config/
└── api.php                                # Server version
hooks/
└── claude-code/
    └── session-start.sh                   # SessionStart hook: prints the project context via /api/context
routes/
├── ai.php                                 # /mcp/memory route with auth:sanctum
└── api.php                                # /api/context (auth:sanctum + throttle:mcp) and /api/auth/{code,token} (throttled)
```

## Security

- `/mcp/memory` requires a Sanctum token and returns 401 without one.
- `user_id` comes from the token, never from tool arguments; every query is scoped to it.
- `get-memory` answers `Memory not found.` for another user's id, the same as for a missing id, so it does not reveal that the memory exists.
- Only the SHA-256 hash of each token is stored.
- A leaked token is revoked with `php artisan memory:revoke <email>`.
- Each user is limited to 60 requests per minute; beyond that the server returns 429.
- Every tool validates its input on the server, including maximum lengths (255 characters for identifiers, 20,000 for content).
- Login codes are stored only as HMAC-SHA256 hashes, expire after 10 minutes, work once, and are burned after 5 wrong attempts; `/api/auth/code` answers the same whether or not the email has an account.

## Contributing

Strict TDD (red → green → refactor), one behavior per test, conventional commits. Keep `config/api.php`, `CHANGELOG.md` and this README in sync on every functional change.
