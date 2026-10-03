#!/usr/bin/env bash
# End-to-end smoke test for the memry Community image.
#
# Boots postgres, runs migrations, starts the app, issues a token through the
# entrypoint and lists the MCP tools over HTTP. Everything is torn down
# (including volumes) on exit unless KEEP=1.
#
#   docker/smoke.sh                 # builds the image with docker compose first
#   SKIP_BUILD=1 MEMRY_IMAGE=memry-server:ci docker/smoke.sh
#
# Requires: docker (with compose), curl, jq.
set -euo pipefail

cd "$(dirname "$0")/.."

export MEMRY_IMAGE="${MEMRY_IMAGE:-memry-server:local}"
PROJECT="${SMOKE_PROJECT:-memry-smoke}"
PORT="${SMOKE_PORT:-18000}"
BASE_URL="http://127.0.0.1:${PORT}"
EXPECTED_TOOLS="get-context get-memory save-memory save-prompt search-memory session-summary"

work_dir="$(mktemp -d)"
env_file="${work_dir}/smoke.env"

compose() {
    docker compose -p "$PROJECT" --env-file "$env_file" "$@"
}

step() { printf '\n==> %s\n' "$*"; }
fail() { printf '\nSMOKE FAILED: %s\n' "$*" >&2; exit 1; }

cleanup() {
    status=$?
    if [ "$status" -ne 0 ] && [ -f "$env_file" ]; then
        compose logs --no-color --tail 50 app migrate 2>/dev/null || true
    fi
    if [ "${KEEP:-0}" != "1" ] && [ -f "$env_file" ]; then
        compose down -v --remove-orphans >/dev/null 2>&1 || true
    fi
    rm -rf "$work_dir"
    exit "$status"
}
trap cleanup EXIT

# A placeholder APP_KEY lets compose interpolate while the image is built.
cat > "$env_file" <<ENV
APP_KEY=placeholder
APP_URL=${BASE_URL}
APP_PORT=${PORT}
DB_PASSWORD=smoke-$(date +%s)
MAIL_MAILER=log
ENV

if [ "${SKIP_BUILD:-0}" != "1" ]; then
    step "Building ${MEMRY_IMAGE}"
    compose build app
fi

step "Generating APP_KEY via the entrypoint"
app_key="$(docker run --rm "$MEMRY_IMAGE" key | tr -d '\r')"
[[ "$app_key" == base64:* ]] || fail "unexpected APP_KEY output: ${app_key}"
sed -i.bak "s|^APP_KEY=.*|APP_KEY=${app_key}|" "$env_file"

step "Starting postgres"
compose up -d --wait postgres

step "Running migrations"
compose run --rm migrate

step "Starting app"
compose up -d --no-deps app

step "Waiting for ${BASE_URL}/up"
for _ in $(seq 1 60); do
    if [ "$(curl -s -o /dev/null -w '%{http_code}' "${BASE_URL}/up")" = "200" ]; then
        ready=1
        break
    fi
    sleep 1
done
[ "${ready:-0}" = "1" ] || fail "/up did not return 200 within 60s"
echo "/up returned 200"

step "Issuing a token via the entrypoint"
token="$(compose run --rm --no-deps -T app token smoke@example.com | tr -d '\r' | sed -n 's/^Token: //p')"
[ -n "$token" ] || fail "no token printed"
echo "token issued (${#token} chars)"

mcp() {
    curl -sS -D "${work_dir}/headers" -X POST "${BASE_URL}/mcp/memory" \
        -H "Authorization: Bearer ${token}" \
        -H 'Content-Type: application/json' \
        -H 'Accept: application/json, text/event-stream' \
        ${session_id:+-H "Mcp-Session-Id: ${session_id}"} \
        -d "$1"
}

step "MCP initialize"
init="$(mcp '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"memry-smoke","version":"1.0"}}}')"
server_name="$(jq -r '.result.serverInfo.name // empty' <<<"$init")"
[ -n "$server_name" ] || fail "initialize failed: ${init}"
session_id="$(grep -i '^mcp-session-id:' "${work_dir}/headers" | awk '{print $2}' | tr -d '\r' || true)"
echo "server: ${server_name}"
mcp '{"jsonrpc":"2.0","method":"notifications/initialized"}' >/dev/null

step "MCP tools/list"
tools_json="$(mcp '{"jsonrpc":"2.0","id":2,"method":"tools/list"}')"
tools="$(jq -r '.result.tools[].name' <<<"$tools_json" | sort | xargs)"
echo "tools: ${tools}"
[ "$tools" = "$EXPECTED_TOOLS" ] || fail "expected tools '${EXPECTED_TOOLS}', got '${tools}'"

step "Email login code with MAIL_MAILER=log"
code_status="$(curl -s -o /dev/null -w '%{http_code}' -X POST "${BASE_URL}/api/auth/code" \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d '{"email":"smoke@example.com"}')"
[ "$code_status" = "202" ] || fail "POST /api/auth/code returned ${code_status}"
echo "POST /api/auth/code returned 202"

printf '\nSMOKE PASSED\n'
