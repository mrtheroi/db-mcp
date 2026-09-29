# Operations

Artisan commands and the legacy Claude Code hook. See [Development](development.md) for setup.

## Artisan commands

```bash
php artisan memory:token you@example.com   # prints a token once
php artisan memory:token you@example.com --create   # non-interactive consoles (e.g. Laravel Cloud)
php artisan memory:revoke you@example.com           # revokes every token of the user
php artisan memory:merge-projects dbmcp memry        # moves every user's memories and prompts of dbmcp into memry
php artisan memory:merge-projects dbmcp memry --email=you@example.com   # only that user's rows
```

The commands trim and lowercase the email, like the login endpoints, so `You@Example.com` finds `you@example.com`.

`memory:token` creates the user after confirmation when the email is unknown; `--create` skips the prompt.

`memory:merge-projects` is non-interactive and runs in a single transaction. It keeps the original timestamps, so moved memories keep their place in the context. When the same user has the same `topic_key` in both projects, nothing is deleted: the rows are moved and the number of collisions is reported so you can resolve them.

## Legacy Claude Code SessionStart hook

memry-cli sets up the agent for end users. This repository still ships the original hook for manual setups.

`hooks/claude-code/session-start.sh` loads the project context into every Claude Code session through `GET /api/context`. The project is the basename of the git top-level of the session `cwd` (or of `cwd` itself outside git); the server normalizes it. It prints a short protocol block for the `memry` MCP tools followed by the context. The token is passed to curl through stdin, so it never appears in the process list. If the config is missing or incomplete, the request fails or it takes longer than 3 seconds, it prints nothing and exits `0`, so it never blocks a session.

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
               "command": "/path/to/memry-server/hooks/claude-code/session-start.sh",
               "timeout": 10
             }
           ]
         }
       ]
     }
   }
   ```
