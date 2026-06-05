#!/usr/bin/env bash
# shellcheck shell=bash
#
# self-test.sh
# Dry-run every backup script. Verifies the shell files parse, the
# `_lib.sh` helpers load, and the cron schedules are syntactically valid.
# Does NOT actually dump a database or upload anything.
#

set -Eeuo pipefail
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"

ok()   { printf '  \033[32mok\033[0m   %s\n' "$*"; }
fail() { printf '  \033[31mFAIL\033[0m %s\n' "$*"; exit 1; }

# 1. bash syntax check on every script.
for f in "${SCRIPT_DIR}"/*.sh; do
    if bash -n "${f}"; then
        ok "syntax: ${f##*/}"
    else
        fail "syntax: ${f##*/}"
    fi
done

# 2. _lib.sh must source without error. (Run in current shell so functions
#    defined by _lib.sh are visible to the later checks.)
if ! source "${SCRIPT_DIR}/_lib.sh" > /dev/null 2>&1; then
    fail "_lib.sh failed to source"
fi
ok "_lib.sh sources cleanly"

# 3. _lib.sh must define all exported helpers.
needed=(log warn err die healthcheck_ping s3_upload ensure_dir prune_local)
for fn in "${needed[@]}"; do
    if declare -F "${fn}" > /dev/null; then
        ok "helper defined: ${fn}"
    else
        fail "helper missing: ${fn}"
    fi
done

# 4. Each schedule must be a valid 5-field cron expression.
schedules=(
    "${BACKUP_DB_SCHEDULE:-0 2 * * *}"
    "${BACKUP_STORAGE_SCHEDULE:-0 3 * * 0}"
    "${BACKUP_PRUNE_SCHEDULE:-15 4 * * *}"
)
for s in "${schedules[@]}"; do
    if [[ "${s}" =~ ^(\*|[0-9*,\/\-]+)( (\*|[0-9*,\/\-]+)){4}$ ]]; then
        ok "schedule: ${s}"
    else
        fail "invalid cron: ${s}"
    fi
done

printf '\nAll backup scripts self-tested successfully.\n'
