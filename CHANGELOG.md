# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.13.1] - 2026-09-29

### Fixed

- **Email case in artisan commands**: `memory:token`, `memory:revoke` and `memory:merge-projects --email` now trim and lowercase the email, like the login endpoints already did
  - `Me@Example.com` finds the existing user `me@example.com` instead of reporting it as missing
  - `memory:token --create` stores the new user's email lowercased, so it can no longer create a second user that differs only in case

---

## [0.13.0] - 2026-09-29

### Added

- **Token revocation endpoint**: `DELETE /api/auth/token` with a Sanctum Bearer token revokes only the token used for the request, so the `memry` CLI can log out from the terminal
  - Answers `204` with no body; the other tokens of the same user stay valid
  - Without a token, or with an invalid or already revoked token, answers `401` with `{"message": "Unauthenticated."}`

---

## [0.12.0] - 2026-09-28

### Added

- **`repo` argument for `session-summary`**: an optional string of at most 255 characters (trimmed, kept as written, not normalized) naming the repository the session worked in, for products that span several repositories
  - With a `repo`, the summary title becomes `Session summary: {project} ({repo})`; without it (or blank) it stays `Session summary: {project}`
  - Stored in the title only: no schema change
- **`memory:merge-projects` command**: `php artisan memory:merge-projects {from} {to} [--email=]` moves the observations and prompts of project `from` into project `to` (both normalized like `project`), for every user or only the user of `--email`
  - Non-interactive: no confirmation prompt; prints how many observations and prompts were moved and how many `topic_key` collisions (same user and `topic_key` in both projects) are left to resolve
  - Collisions are never deleted: both rows end up in `to` and must be resolved by hand
  - Moved rows keep their timestamps, so `get-context` keeps its order, and stay searchable under `to` (`search_vector` only covers title and content)
  - Fails without moving anything when `from` and `to` are the same after normalization, when a name is blank, or when the `--email` user does not exist
  - Runs in a single transaction

### Changed

- **`get-context` shows `## Recent sessions` instead of `## Latest session`**, so parallel sessions in different repositories of the same project are all visible (also in `GET /api/context`)
  - The last 3 `session_summary` memories of the project, most recently updated first: the newest in full, the other two as one line each with their `updated_at` date (`Y-m-d H:i`, UTC) and a 300-character preview of their content
  - Every other layer and bound is unchanged

---

## [0.11.1] - 2026-09-28

### Fixed

- Login code emails failed with `Class "Resend" not found` (HTTP 500) when `MAIL_MAILER=resend`: the `resend/resend-php` package required by Laravel's Resend transport is now installed

---

## [0.11.0] - 2026-09-28

### Added

- **Passwordless email login**: a one-time code sent by email is exchanged for a Sanctum token, so the `memry` CLI can log in entirely from the terminal
  - `POST /api/auth/code` with `{email}` (required string email of at most 255 characters, trimmed and lowercased) emails a random 6-digit code valid for 10 minutes and always answers `202` with `{"message": "If the email is valid, a login code has been sent."}`
  - Issuing a new code invalidates the previous unused codes of the email; only an HMAC-SHA256 hash of the code (keyed with the app key) is stored, in the new `login_codes` table
  - `POST /api/auth/token` with `{email, code}` (`code` is a 6-digit string) answers `200` with `{"token": "..."}`, a Sanctum token named `memry-cli` that authenticates the MCP route and `GET /api/context`
  - Signup is open: an unknown email creates the user (name from the email local part, random password, email marked as verified); a known email reuses its user
  - A wrong, expired, already used or superseded code answers `422` with `{"message": "Invalid or expired code."}`; each wrong code counts as an attempt and the 5th wrong attempt burns the code, so even the right code fails afterwards
  - Rate limited: `auth-code` allows 3 requests per 10 minutes per email and 10 per hour per IP, `auth-token` allows 20 requests per minute per IP (`429` when exceeded)

---

## [0.10.0] - 2026-09-28

### Added

- **`hooks/claude-code/session-start.sh`**: a Claude Code `SessionStart` hook that loads the memry context of the current project into the session through `GET /api/context`
  - Reads `url` and `token` from `${MEMRY_CONFIG:-~/.config/memry/config.json}`; the token is only sent in the `Authorization` header, passed to curl through stdin (`-H @-`) so it never appears in the process list
  - The project is the basename of the git top-level of the session `cwd` (the basename of `cwd` outside git, the working directory when `cwd` is empty), URL-encoded and normalized by the server
  - Prints a short protocol block for the `db-memory` MCP tools (additive to Engram) followed by the endpoint body
  - Fails silently: a missing or incomplete config, a curl error, an HTTP error or the 3-second timeout print nothing, and the script always exits `0`

---

## [0.9.0] - 2026-09-28

### Added

- **`GET /api/context?project={name}`**: a plain HTTP endpoint that returns exactly the text of the `get-context` tool, so a shell hook (e.g. a Claude Code `SessionStart` hook using `curl`) can load the project context without speaking MCP JSON-RPC
  - Authenticated with the same Sanctum bearer tokens as the MCP route (`401` without a valid token) and scoped to the authenticated user
  - Shares the `mcp` rate limiter (60 requests per minute per user, counted together with MCP requests)
  - `project` is a required string of at most 255 characters (`422` with the validation errors otherwise) and is normalized like in `get-context`
  - Responds `200` with `Content-Type: text/plain; charset=UTF-8`, including `No context found for project {project}.` when there is nothing to show

---

## [0.8.0] - 2026-09-28

### Changed

- **`get-context` returns a bounded, layered context** instead of the 20 most recent memories in full, so its size stays predictable when injected at every session start
  - `## Latest session`: only the most recent `session_summary` of the project, in full; older summaries are omitted because each summary is cumulative
  - `## Project knowledge`: up to 20 memories with a `topic_key`, most recently updated first, each as one line with a 300-character preview of its content (newlines collapsed, `…` when truncated)
  - `## Recent memories`: up to 10 other memories, most recently updated first, title only
  - Empty sections are omitted, and the output ends with a hint to call `get-memory` with an id to read a memory in full
  - Still scoped to the authenticated user and the normalized project; `No context found for project {project}.` when there is nothing to show

---

## [0.7.0] - 2026-09-28

### Changed

- **Project names are normalized**: every `project` is trimmed, lowercased, and has repeated `--` collapsed to `-` and `__` to `_` (the same rule as Engram's `CanonicalizeProjectName`), so `dbmcp`, `dbMcp` and ` DbMcp ` are the same project
  - Applied on write: `save-memory`, `session-summary` and `save-prompt` store the normalized name; an empty or whitespace-only `project` is stored as no project
  - Applied on query: `get-context` and the `project` filter of `search-memory` normalize the argument, so `DbMcp` finds memories saved as `dbmcp`
  - The `topic_key` upsert matches across spellings: saving with `project: DbMcp` updates the existing `dbmcp` memory instead of creating a new one
  - **Note**: existing rows are not migrated; names stored before this version keep their original spelling

---

## [0.6.0] - 2026-09-25

### Added

- **`get-memory` tool**: returns the full content of one memory by its `id`, in the same `#id [type] title` format as `search-memory`, so agents can read a memory in full on demand
  - Validates `id` as a required integer of at least 1

### Security

- **Users can only read their own memories**: an id that belongs to another user returns the same `Memory not found.` as an id that does not exist, so the existence of other users' memories is not leaked

---

## [0.5.0] - 2026-09-25

### Added

- **`project` filter on `search-memory`**: an optional `project` argument returns only the memories of that project; without it, the search still covers all of the user's projects
  - Validated with `max:255`, like the other identifiers

---

## [0.4.0] - 2026-09-25

### Added

- **`memory:revoke` command**: `php artisan memory:revoke {email}` revokes every Sanctum token of a user and reports how many were revoked
  - Fails with a clear message when the user does not exist

### Security

- **Token revocation without `tinker`**: a leaked token can be invalidated in one command; the user's other agents are disconnected too, and other users' tokens are untouched

---

## [0.3.1] - 2026-09-25

### Fixed

- **Long values no longer crash the tools**: values longer than a `varchar(255)` column used to fail in Postgres and return "An internal server error occurred."; tools now return a validation message instead
- **Session summaries with long project names**: the generated title `Session summary: {project}` is cut to 255 characters so a 255-character project name is accepted; the full name stays in `project`

### Security

- **Maximum input sizes**: every tool validates `max:255` for identifiers (`session_id`, `type`, `title`, `project`, `topic_key`, `query`) and `max:20000` for `content`, so a single call cannot store megabytes

---

## [0.3.0] - 2026-09-25

### Security

- **Rate limit on `/mcp/memory`**: each user can make at most 60 requests per minute; beyond that the server returns `429 Too Many Requests`
  - Named limiter `mcp` in `AppServiceProvider`, keyed by the authenticated user, applied after `auth:sanctum`
  - Protects against agents stuck in a loop and leaked tokens

---

## [0.2.0] - 2026-09-25

### Added

- **`--create` option for `memory:token`**: `php artisan memory:token {email} --create` creates a missing user without asking
  - Needed in non-interactive consoles (such as the Laravel Cloud command runner), where the confirmation defaults to "no"

---

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
