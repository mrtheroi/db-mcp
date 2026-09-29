# Development

Developer reference for memry-server. End users install the [memry CLI](https://github.com/mrtheroi/memry-cli) instead of running this server.

Other references:

- [API](api.md): HTTP endpoints and MCP tools
- [Operations](operations.md): artisan commands and the legacy SessionStart hook
- [Security](security.md): authentication, token and login code handling, rate limits

## How it works

```
Agent (Claude Code) ──POST /mcp/memory + Bearer token──▶ auth:sanctum ──▶ MemoryServer
                                                                         │
                                        tools/call ──▶ Tool ──▶ Use case / Port ──▶ Postgres
```

The agent never touches the database: it discovers the tools with `tools/list` and invokes them with `tools/call`.

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
```

See [Operations](operations.md) for the other artisan commands.

Connect Claude Code directly to a local or self-hosted server (memry-cli does this for end users):

```bash
claude mcp add --transport http memry https://<your-host>/mcp/memory \
  --header "Authorization: Bearer <token>"
```

## Configuration

Names and purpose only; never commit values.

| Variable | Purpose |
| --- | --- |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Postgres connection |
| `API_VERSION` | Overrides the version in `config/api.php` (optional) |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Mail transport used to send login codes |
| `MAIL_FROM_NAME` | Sender name of the login code email (falls back to `APP_NAME`, see `config/mail.php`) *(added from code)* |
| `APP_URL` | Public base URL of the app (`config/app.php`); also the default mail EHLO domain *(added from code)* |

## Scheduler

Expired login codes are deleted by a daily `model:prune` job (`routes/console.php`), so the scheduler must run: on Laravel Cloud, enable the scheduler on the App compute cluster; elsewhere, run `php artisan schedule:work` or a cron entry calling `php artisan schedule:run` every minute.

## Tests and code style

Tests run against a local Postgres 17 container, **never** against the production database (`RefreshDatabase` wipes tables):

```bash
docker run -d --name dbmcp-pg-test -e POSTGRES_USER=dbmcp -e POSTGRES_PASSWORD=secret \
  -e POSTGRES_DB=dbmcp_testing -p 5432:5432 pgvector/pgvector:pg17
./vendor/bin/pest
```

`phpunit.xml` overrides the `DB_*` variables to point at that container.

Format with Laravel Pint *(added from code: `laravel/pint` is installed)*:

```bash
./vendor/bin/pint
```

## Deployment

*(Added: not previously documented in the README.)*

- Production runs on Laravel Cloud at `https://api.memry.com.mx`, with Laravel Cloud Serverless Postgres.
- Set `APP_URL` to the public URL and `MAIL_FROM_NAME` / `MAIL_FROM_ADDRESS` for the login email sender.
- Enable the scheduler on the App compute cluster (see [Scheduler](#scheduler)).
- Laravel Cloud consoles are non-interactive: use `php artisan memory:token <email> --create` there (see [Operations](operations.md)).

## Architecture

Screaming + hexagonal: the domain knows nothing about Laravel, Eloquent or MCP; tools are thin input adapters.

```
app/
├── Console/
│   └── Commands/
│       ├── IssueMemoryToken.php           # memory:token — issues a Sanctum token, creates the user after confirmation
│       ├── MergeMemoryProjects.php        # memory:merge-projects — moves memories and prompts between projects
│       └── RevokeMemoryTokens.php         # memory:revoke — revokes every token of a user
├── Http/
│   └── Controllers/
│       ├── Auth/
│       │   ├── LoginCodeController.php    # POST /api/auth/code — emails a one-time login code
│       │   ├── RevokeTokenController.php  # DELETE /api/auth/token — revokes the token used for the request
│       │   └── TokenController.php        # POST /api/auth/token — exchanges the code for a memry-cli token
│       └── ContextController.php          # GET /api/context — plain-text project context
├── Mail/
│   └── LoginCodeMail.php                  # Login code mail (HTML and plain-text parts)
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
│   │   ├── MergeProjects.php              # Use case: move a project's rows into another, counting topic_key collisions
│   │   └── SaveObservation.php            # Use case: upsert by topic_key
│   ├── Domain/
│   │   ├── MemoryRepository.php           # Port: save, find, findByTopicKey, search, recentSessionSummaries, withTopicKey, recentWithoutTopicKey, countTopicKeyCollisions, moveToProject
│   │   ├── Observation.php                # Immutable memory entity
│   │   ├── ProjectName.php                # Normalizes project names
│   │   ├── PromptRepository.php           # Port: save and move prompts
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

## Contributing

Strict TDD (red → green → refactor), one behavior per test, conventional commits.

Versioning: the version lives in `config/api.php` and follows Semantic Versioning; every functional change bumps it and adds an entry to `CHANGELOG.md` (Keep a Changelog format). Keep the version line in `README.md` and the reference in `docs/` in sync with the change.
