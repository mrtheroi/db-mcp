#!/usr/bin/env bash
# Claude Code SessionStart hook: prints the memry context of the current project.
# Reads the hook JSON on stdin and the config at ${MEMRY_CONFIG:-~/.config/memry/config.json}
# ({"url": "...", "token": "..."}). Any failure prints nothing and exits 0,
# so the hook never blocks a session.

input=$(cat)
cwd=$(jq -r '.cwd // empty' <<<"$input" 2>/dev/null)
cwd="${cwd:-$PWD}"

config="${MEMRY_CONFIG:-$HOME/.config/memry/config.json}"
url=$(jq -r '.url // empty' "$config" 2>/dev/null)
token=$(jq -r '.token // empty' "$config" 2>/dev/null)
[ -n "$url" ] && [ -n "$token" ] || exit 0

root=$(git -C "$cwd" rev-parse --show-toplevel 2>/dev/null) || root="$cwd"
project=$(basename "$root")
encoded=$(jq -rn --arg p "$project" '$p|@uri')

# The header goes through stdin so the token never shows up in the process list.
body=$(curl -sf --max-time 3 -H @- "$url/api/context?project=$encoded" <<<"Authorization: Bearer $token") || exit 0

cat <<TXT
## memry memory (project: $project)
memry is available through the \`db-memory\` MCP tools, alongside Engram.
- Use get-memory with an id to read a memory from the context below in full, and search-memory to find older ones.
- Save decisions, bug fixes and discoveries with save-memory (project "$project", with a topic_key for evolving topics).
- Before ending the session, save a summary with session-summary (project "$project").

TXT
printf '%s\n' "$body"
