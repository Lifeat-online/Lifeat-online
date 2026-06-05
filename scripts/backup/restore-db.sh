#!/usr/bin/env bash
# shellcheck shell=bash
#
# restore-db.sh
# Restore a database dump produced by backup-db.sh.
#
# Usage:
#   scripts/backup/restore-db.sh <archive-path> [--from-s3 <key>]
#
#   archive-path         Absolute path to a .sql.gz file (local).
#   --from-s3 <key>      Optional. Download the key from the configured
#                        S3-compatible bucket into a temp file first.
#
# Examples:
#   scripts/backup/restore-db.sh /opt/lifeat/storage/app/backups/db/lifeat-2026-06-05_020000.sql.gz
#   scripts/backup/restore-db.sh /tmp/x.sql.gz --from-s3 db/lifeat-2026-06-05_020000.sql.gz
#
# SAFETY: This script runs DROP DATABASE on the target. It will prompt for
# confirmation unless --yes is supplied.
#

set -Eeuo pipefail
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
# shellcheck source=_lib.sh
source "${SCRIPT_DIR}/_lib.sh"

ARCHIVE="${1:-}"
FROM_S3_KEY=""
ASSUME_YES="false"

if [[ -z "${ARCHIVE}" ]]; then
    die "Usage: $0 <archive-path> [--from-s3 <key>] [--yes]"
fi
shift
while [[ $# -gt 0 ]]; do
    case "$1" in
        --from-s3) FROM_S3_KEY="$2"; shift 2 ;;
        --yes|-y)  ASSUME_YES="true"; shift ;;
        *) die "Unknown argument: $1" ;;
    esac
done

# ---------------------------------------------------------------------------
# Optionally download from S3 first.
# ---------------------------------------------------------------------------
if [[ -n "${FROM_S3_KEY}" ]]; then
    if [[ "${BACKUP_S3_ENABLED}" != "true" || -z "${BACKUP_S3_BUCKET}" ]]; then
        die "--from-s3 requested but BACKUP_S3 is not configured."
    fi
    if ! command -v aws > /dev/null 2>&1; then
        die "aws CLI not installed"
    fi
    endpoint_args=()
    [[ -n "${BACKUP_S3_ENDPOINT}" ]] && endpoint_args+=(--endpoint-url "${BACKUP_S3_ENDPOINT}")

    ARCHIVE="$(mktemp -t lifeat-restore-XXXXXX.sql.gz)"
    trap 'rm -f "${ARCHIVE}"' EXIT

    log "Downloading s3://${BACKUP_S3_BUCKET}/${BACKUP_S3_PREFIX}/${FROM_S3_KEY} -> ${ARCHIVE}"
    AWS_ACCESS_KEY_ID="${BACKUP_S3_ACCESS_KEY}" \
    AWS_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET_KEY}" \
    AWS_DEFAULT_REGION="${BACKUP_S3_REGION}" \
    aws s3 cp "s3://${BACKUP_S3_BUCKET}/${BACKUP_S3_PREFIX}/${FROM_S3_KEY}" "${ARCHIVE}" \
        --only-show-errors "${endpoint_args[@]}" --region "${BACKUP_S3_REGION}"
fi

if [[ ! -f "${ARCHIVE}" ]]; then
    die "Archive not found: ${ARCHIVE}"
fi

log "Inspecting archive ${ARCHIVE}"
if ! gzip -t "${ARCHIVE}" 2> /dev/null; then
    die "Archive is not a valid gzip file"
fi

# ---------------------------------------------------------------------------
# Confirm
# ---------------------------------------------------------------------------
if [[ "${ASSUME_YES}" != "true" ]]; then
    cat <<EOF

=========================================================
  DESTRUCTIVE OPERATION
=========================================================
  Target:   ${DB_CONNECTION}://${DB_HOST:-127.0.0.1}:${DB_PORT:-3306}/${DB_DATABASE:-<unset>}
  Archive:  ${ARCHIVE}
  Size:     $(du -h "${ARCHIVE}" | cut -f1)
=========================================================
EOF
    read -rp "Type 'yes' to continue: " confirm
    [[ "${confirm}" == "yes" ]] || die "Aborted by user"
fi

# ---------------------------------------------------------------------------
# Restore
# ---------------------------------------------------------------------------
if [[ "${DB_CONNECTION}" == "mysql" || "${DB_CONNECTION}" == "mariadb" ]]; then
    if ! command -v mysql > /dev/null 2>&1; then
        die "mysql client not installed"
    fi
    if [[ -z "${DB_DATABASE:-}" ]]; then
        die "DB_DATABASE is not set in .env"
    fi
    log "Recreating database ${DB_DATABASE}"
    mysql --user="${DB_USERNAME:-root}" --password="${DB_PASSWORD:-}" \
        --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" \
        -e "DROP DATABASE IF EXISTS \`${DB_DATABASE}\`; CREATE DATABASE \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    log "Importing archive"
    gunzip -c "${ARCHIVE}" | mysql --user="${DB_USERNAME:-root}" --password="${DB_PASSWORD:-}" \
        --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" \
        "${DB_DATABASE}"
elif [[ "${DB_CONNECTION}" == "sqlite" ]]; then
    DB_DATABASE_PATH="${DB_DATABASE:-${PROJECT_ROOT}/database/database.sqlite}"
    if [[ -f "${DB_DATABASE_PATH}" ]]; then
        cp -p "${DB_DATABASE_PATH}" "${DB_DATABASE_PATH}.pre-restore-$(date +%s)"
    fi
    log "Restoring SQLite dump to ${DB_DATABASE_PATH}"
    gunzip -c "${ARCHIVE}" > "${DB_DATABASE_PATH}"
else
    die "Unsupported DB_CONNECTION='${DB_CONNECTION}'"
fi

log "Restore complete. Run 'php artisan migrate --pretend' to verify schema freshness."
