# Self-hosting (memry Community)

Run your own memry server with Docker. The image bundles the Laravel app served by FrankenPHP; you bring PostgreSQL.

## Requirements

- Docker with Docker Compose v2
- PostgreSQL 14 or newer. The example Compose file starts one for you (`postgres:16-alpine`); full-text search relies on PostgreSQL, so other databases are not supported.

The server sends no telemetry. It only talks to your database and, if you configure it, your SMTP server.

## Quick start

Use a checkout dedicated to the server. Compose reads the `.env` next to `docker-compose.yml`, so do not run it from a development checkout that already has its own `.env`; clone the repository again instead:

```bash
git clone https://github.com/mrtheroi/memry-server.git memry-community
cd memry-community
```

Then:

```bash
cp -n docker/community.env.example .env               # -n never overwrites an existing .env
docker compose build                                  # builds memry-server:local
docker compose run --rm --no-deps app key             # prints an APP_KEY
# edit .env: paste APP_KEY, set APP_URL and a strong DB_PASSWORD
docker compose run --rm migrate
docker compose up -d app scheduler
curl http://localhost:8000/up                         # 200 when healthy
```

Services in `docker-compose.yml`:

| Service | What it does |
| --- | --- |
| `app` | Serves HTTP on container port 8000 (published on `APP_PORT`, default 8000) |
| `scheduler` | Runs `schedule:work` (daily pruning of expired login codes) |
| `migrate` | One-off: runs migrations and exits |
| `postgres` | PostgreSQL 16 with the `postgres-data` volume |

The container runs as a non-root user and logs to stderr (`docker compose logs app`).

### Entrypoint commands

The image entrypoint accepts these commands (`docker compose run --rm app <command>`):

| Command | Effect |
| --- | --- |
| `serve` (default) | Caches config, routes and views, then starts FrankenPHP |
| `migrate` | `php artisan migrate --force --isolated` |
| `scheduler` | `php artisan schedule:work` |
| `token <email>` | Creates the user if needed and prints a new token |
| `key` | Prints a new `APP_KEY` |
| anything else | Passed to `php artisan` (for example `memory:revoke you@example.com`) |

Every command except `key` refuses to start when `APP_KEY` is empty or `DB_CONNECTION` is not `pgsql`.

## Using an existing PostgreSQL

Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (and `DB_SSLMODE`, for example `require`) in `.env`, then remove the `postgres` service and the `depends_on` block from your copy of `docker-compose.yml`. The database must exist; migrations create the tables.

## Migrations

Run migrations after the first install and after every upgrade:

```bash
docker compose run --rm migrate
```

Alternatively set `AUTO_MIGRATE=true` so `app` migrates before it starts serving. Migrations take a database lock, so several containers starting at once do not run them twice.

## Users and tokens

There is no sign-up page. Create a user and token from the server:

```bash
docker compose run --rm app token you@example.com     # prints "Token: ..." once
docker compose run --rm app memory:revoke you@example.com
```

Tokens are shown once; store them like passwords.

## Connecting agents

With the [memry CLI](https://github.com/mrtheroi/memry-cli) 0.6.0 or newer:

```bash
memry setup --url https://memry.example.com --token   # asks for the token, hidden
```

`--url` is required with `--token`, so the token is only sent to your server. For scripts, `--token="$MEMRY_TOKEN"` skips the prompt. Keep the quotes: tokens contain a `|`, which the shell would otherwise read as a pipe. A token typed on the command line ends up in the shell history.

Or add the MCP server to Claude Code directly:

```bash
claude mcp add --transport http memry https://memry.example.com/mcp/memory \
  --header "Authorization: Bearer <token>"
```

## Email login (optional)

Users can also get a token themselves through the emailed login code flow (`POST /api/auth/code`, then `POST /api/auth/token`). By default `MAIL_MAILER=log`: codes are only written to the container logs, which is fine for a single-user setup. To deliver them by email, configure SMTP in `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=memry@example.com
```

## Reverse proxy and TLS

The container speaks plain HTTP. Put it behind a TLS-terminating reverse proxy (Caddy, nginx, Traefik, a cloud load balancer) and:

- set `APP_URL` to the public `https://` URL;
- set `TRUSTED_PROXIES` so forwarded headers are honoured: `*` trusts the proxy calling the container, or list proxy addresses / CIDR ranges separated by commas.

Agents send bearer tokens on every request, so do not expose the server over plain HTTP on the internet.

## Upgrades

```bash
git pull
docker compose build
docker compose run --rm migrate
docker compose up -d app scheduler
```

## Backups

All state lives in PostgreSQL:

```bash
docker compose exec -T postgres pg_dump -U memry -Fc memry > memry-$(date +%F).dump
# restore into an empty database:
docker compose exec -T postgres pg_restore -U memry -d memry --clean --if-exists < memry-2026-01-01.dump
```

Keep `APP_KEY` stable across restores and upgrades: login codes are signed with it (tokens are not).

## Smoke test

`docker/smoke.sh` builds the image, starts PostgreSQL, migrates, starts the app, issues a token and checks that `/mcp/memory` lists the six memory tools. It removes its containers and volumes when done. CI runs the same script (`.github/workflows/docker.yml`).
