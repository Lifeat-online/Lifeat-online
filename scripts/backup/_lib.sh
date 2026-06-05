#!/usr/bin/env bash
# shellcheck shell=bash
#
# Common helpers sourced by every script in scripts/backup/.
# - Loads .env safely.
# - Resolves paths and credentials.
# - Provides log(), die(), optional S3 upload, and healthcheck ping.
#
# This file is intentionally side-effect-free: it does NOT run any backup.
#

set -Eeuo pipefail

# ---------------------------------------------------------------------------
# Locate project root (parent of scripts/).
# ---------------------------------------------------------------------------
SCRIPTS_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
PROJECT_ROOT="$(cd -- "${SCRIPTS_DIR}/.." &> /dev/null && pwd)"

# ---------------------------------------------------------------------------
# Load .env if present. We only export BACKUP_* and DB_* values so we do not
# pollute the shell with the entire application config.
# ---------------------------------------------------------------------------
if [[ -f "${PROJECT_ROOT}/.env" ]]; then
    set -a
    # shellcheck disable=SC1091
    source "${PROJECT_ROOT}/.env"
    set +a
fi

# ---------------------------------------------------------------------------
# Resolve effective configuration with sensible defaults.
# ---------------------------------------------------------------------------
BACKUP_LOCAL_PATH="${BACKUP_LOCAL_PATH:-${PROJECT_ROOT}/storage/app/backups}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
BACKUP_S3_ENABLED="${BACKUP_S3_ENABLED:-false}"
BACKUP_S3_BUCKET="${BACKUP_S3_BUCKET:-}"
BACKUP_S3_PREFIX="${BACKUP_S3_PREFIX:-lifeat}"
BACKUP_S3_REGION="${BACKUP_S3_REGION:-us-east-1}"
BACKUP_S3_ENDPOINT="${BACKUP_S3_ENDPOINT:-}"
BACKUP_S3_PATH_STYLE="${BACKUP_S3_PATH_STYLE:-true}"
BACKUP_S3_ACCESS_KEY="${BACKUP_S3_ACCESS_KEY:-${AWS_ACCESS_KEY_ID:-}}"
BACKUP_S3_SECRET_KEY="${BACKUP_S3_SECRET_KEY:-${AWS_SECRET_ACCESS_KEY:-}}"
BACKUP_S3_MAX_BYTES="${BACKUP_S3_MAX_BYTES:-5368709120}"
BACKUP_HEALTHCHECK_URL="${BACKUP_HEALTHCHECK_URL:-}"

DB_CONNECTION="${DB_CONNECTION:-sqlite}"

# ---------------------------------------------------------------------------
# Logging – always line-buffered, never lost on a redirected stdout.
# ---------------------------------------------------------------------------
_ts() { date -u +"%Y-%m-%dT%H:%M:%SZ"; }

log()  { printf '%s [INFO]  %s\n' "$(_ts)" "$*" >&2; }
warn() { printf '%s [WARN]  %s\n' "$(_ts)" "$*" >&2; }
err()  { printf '%s [ERROR] %s\n' "$(_ts)" "$*" >&2; }

die() {
    err "$*"
    healthcheck_ping "fail" || true
    exit 1
}

# ---------------------------------------------------------------------------
# Healthchecks.io ping.
#   $1 = "ok" | "fail" | ""   (empty means "starting"; not used here)
# ---------------------------------------------------------------------------
healthcheck_ping() {
    local status="${1:-ok}"
    [[ -z "${BACKUP_HEALTHCHECK_URL}" ]] && return 0

    local url="${BACKUP_HEALTHCHECK_URL}"
    if [[ "${status}" == "fail" ]]; then
        url="${url}/fail"
    fi

    # Silent on success so cron output stays clean.
    if ! curl --silent --show-error --fail --max-time 15 -o /dev/null "${url}"; then
        warn "Healthcheck ping failed: ${url}"
    fi
}

# ---------------------------------------------------------------------------
# Optional S3 upload via the `aws` CLI.
#   $1 = absolute path to local file
#   $2 = remote key (e.g. db/lifeat-2026-06-05.sql.gz)
#
# No-op if BACKUP_S3_ENABLED != "true" or the bucket is empty.
# ---------------------------------------------------------------------------
s3_upload() {
    local file="$1"
    local key="$2"

    if [[ "${BACKUP_S3_ENABLED}" != "true" || -z "${BACKUP_S3_BUCKET}" ]]; then
        log "S3 upload skipped (BACKUP_S3_ENABLED=${BACKUP_S3_ENABLED})."
        return 0
    fi

    if ! command -v aws > /dev/null 2>&1; then
        die "BACKUP_S3_ENABLED=true but the 'aws' CLI is not installed."
    fi

    local size
    size=$(stat -c %s "${file}" 2> /dev/null || stat -f %z "${file}")
    if (( size > BACKUP_S3_MAX_BYTES )); then
        die "Backup file ${file} is ${size} bytes, exceeds BACKUP_S3_MAX_BYTES=${BACKUP_S3_MAX_BYTES}."
    fi

    local endpoint_args=()
    if [[ -n "${BACKUP_S3_ENDPOINT}" ]]; then
        endpoint_args+=(--endpoint-url "${BACKUP_S3_ENDPOINT}")
    fi
    if [[ "${BACKUP_S3_PATH_STYLE}" == "true" ]]; then
        AWS_S3_FORCE_PATH_STYLE=true
        export AWS_S3_FORCE_PATH_STYLE
    fi

    local s3_uri="s3://${BACKUP_S3_BUCKET}/${BACKUP_S3_PREFIX}/${key}"

    log "Uploading ${file} -> ${s3_uri}"
    AWS_ACCESS_KEY_ID="${BACKUP_S3_ACCESS_KEY}" \
    AWS_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET_KEY}" \
    AWS_DEFAULT_REGION="${BACKUP_S3_REGION}" \
    aws s3 cp "${file}" "${s3_uri}" \
        --only-show-errors \
        "${endpoint_args[@]}" \
        --region "${BACKUP_S3_REGION}" \
        --storage-class STANDARD_IA
}

# ---------------------------------------------------------------------------
# ensure_dir – mkdir -p with strict error handling.
# ---------------------------------------------------------------------------
ensure_dir() {
    local dir="$1"
    if ! mkdir -p "${dir}"; then
        die "Failed to create directory: ${dir}"
    fi
}

# ---------------------------------------------------------------------------
# prune_local – remove files older than BACKUP_RETENTION_DAYS.
# ---------------------------------------------------------------------------
prune_local() {
    local dir="$1"
    [[ -d "${dir}" ]] || return 0
    log "Pruning ${dir} (retention=${BACKUP_RETENTION_DAYS}d)"
    # -mtime +N matches files modified MORE than N days ago.
    find "${dir}" -type f -mtime "+${BACKUP_RETENTION_DAYS}" -print -delete >&2
}

# ---------------------------------------------------------------------------
# Export helpers for child scripts.
# ---------------------------------------------------------------------------
export -f log warn err die _ts
export -f healthcheck_ping s3_upload ensure_dir prune_local
export PROJECT_ROOT SCRIPTS_DIR
export BACKUP_LOCAL_PATH BACKUP_RETENTION_DAYS
export BACKUP_S3_ENABLED BACKUP_S3_BUCKET BACKUP_S3_PREFIX BACKUP_S3_REGION
export BACKUP_S3_ENDPOINT BACKUP_S3_PATH_STYLE BACKUP_S3_ACCESS_KEY BACKUP_S3_SECRET_KEY
export BACKUP_S3_MAX_BYTES BACKUP_HEALTHCHECK_URL
export DB_CONNECTION
