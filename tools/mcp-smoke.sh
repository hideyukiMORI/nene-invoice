#!/usr/bin/env bash
# Local MCP smoke helper for NeNe Invoice (ADR 0021 / dev-only).
# Pipes JSON-RPC messages at tools/local-mcp-server.php and prints the responses.
#
# `initialize` / `tools/list` need no running API. `tools/call` needs the app up
# (and MySQL + migrations for anything that reads the DB):
#   docker compose up -d app
#   docker compose up -d mysql && docker compose run --rm app composer migrations:migrate
#
# Usage:
#   bash tools/mcp-smoke.sh                          # initialize + tools/list
#   bash tools/mcp-smoke.sh getHealth '{}'           # + a read-tool call
#   bash tools/mcp-smoke.sh listInvoices '{"limit":5}'
#
# Environment:
#   NENE2_LOCAL_API_BASE_URL       API base URL (default: http://localhost:8510)
#   NENE2_LOCAL_MCP_BEARER         pre-issued bearer (needed for /admin/* tools)
#   NENE2_LOCAL_MCP_INCLUDE_ADMIN  set to 1 to also expose the admin tools
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TOOL="${1:-}"
ARGS="${2:-{}}"

MESSAGES=(
    '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"mcp-smoke","version":"0.0.0"}}}'
    '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
)

if [[ -n "${TOOL}" ]]; then
    MESSAGES+=("{\"jsonrpc\":\"2.0\",\"id\":3,\"method\":\"tools/call\",\"params\":{\"name\":\"${TOOL}\",\"arguments\":${ARGS}}}")
fi

printf '%s\n' "${MESSAGES[@]}" | php "${ROOT}/tools/local-mcp-server.php"
