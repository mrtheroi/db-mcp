# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-09-25

### Added

- **Memory MCP server**: `MemoryServer` exposed over HTTP at `POST /mcp/memory` with `laravel/mcp`
  - Agent instructions: recall before working, save after deciding, never store secrets
- **`save-memory` tool**: saves observations (decisions, bug fixes, discoveries, conventions)
  - Upsert by `topic_key`: the same key in the same project and scope updates instead of duplicating (`SaveObservation` use case)
  - Server-side validation of `session_id`, `type`, `title` and `content`
- **`search-memory` tool**: Postgres full-text search
  - Generated `search_vector` column (`tsvector`, `english` configuration) with a GIN index
  - Ranking with `ts_rank`: the title weighs more than the content
  - `limit` validated between 1 and 20 (default 10), and "No memories found." when nothing matches
- **`session-summary` tool**: saves the session summary as an observation of type `session_summary`
- **`get-context` tool**: returns the 20 most recently updated memories of a project
- **`save-prompt` tool**: stores the user prompt verbatim in the `user_prompts` table
- **`memory:token` command**: `php artisan memory:token {email}` issues a Sanctum token and creates the user only after confirmation
- **Hexagonal architecture**: `app/Memory/{Domain,Application,Infrastructure}` with `MemoryRepository` and `PromptRepository` ports
- **Test suite**: 34 Pest tests against a local Postgres 17 in Docker
- **Versioning**: server version in `config/api.php`

### Security

- **Authentication**: `/mcp/memory` requires a Sanctum token (`auth:sanctum`) and returns 401 without a valid one
- **User isolation**: `user_id` comes from the token, never from tool arguments, and every query is scoped to it
- **Tokens**: only the SHA-256 hash is stored; the plain-text token is shown once
