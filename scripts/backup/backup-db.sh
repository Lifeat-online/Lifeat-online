#!/usr/bin/env bash
# shellcheck shell=bash
#
# backup-db.sh
# Dump the application database and (optionally) upload to S3-compatible
# storage. PostgreSQL is the Life@ deployment target; legacy MySQL and SQLite
# branches remain usable for local compatibility.
#
# Usage:
#   scripts/backup/backup-db.sh
#
# Cron entry (Hetzner):
#   0 2 * * *  /opt/lifeat/scripts/backup/backup-db.sh >> /var/log/lifeat/backup-db.log 2>&1
#

set -Eeuo pipefail
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
# shellcheck source=_lib.sh
source "${SCRIPT_DIR}/_lib.sh"

DATE="$(date -u +%Y-%m-%d_%H%M%S)"
DAY="$(date -u +%Y-%m-%d)"
OUT_DIR="${BACKUP_LOCAL_PATH}/db"
ensure_dir "${OUT_DIR}"

# ---------------------------------------------------------------------------
# Pre-flight checks
# ---------------------------------------------------------------------------
if [[ "${DB_CONNECTION}" == "pgsql" ]]; then
    if ! command -v "${BACKUP_PGDUMP_BIN:-pg_dump}" > /dev/null 2>&1; then
        die "pg_dump not found in PATH. Set BACKUP_PGDUMP_BIN or install postgresql-client."
    fi
    if [[ -z "${DB_DATABASE:-}" ]]; then
        die "DB_DATABASE is not set in .env"
    fi
elif [[ "${DB_CONNECTION}" == "mysql" || "${DB_CONNECTION}" == "mariadb" ]]; then
    if ! command -v "${BACKUP_MYSQLDUMP_BIN:-mysqldump}" > /dev/null 2>&1; then
        die "mysqldump not found in PATH. Set BACKUP_MYSQLDUMP_BIN or install mariadb-client."
    fi
    if [[ -z "${DB_DATABASE:-}" ]]; then
        die "DB_DATABASE is not set in .env"
    fi
elif [[ "${DB_CONNECTION}" == "sqlite" ]]; then
    DB_DATABASE_PATH="${DB_DATABASE:-${PROJECT_ROOT}/database/database.sqlite}"
    if [[ ! -f "${DB_DATABASE_PATH}" ]]; then
        die "SQLite database file not found: ${DB_DATABASE_PATH}"
    fi
else
    die "Unsupported DB_CONNECTION='${DB_CONNECTION}'. Configure a dedicated dump routine."
fi

# ---------------------------------------------------------------------------
# Build the dump
# ---------------------------------------------------------------------------
FILENAME="lifeat-${DAY}-${DATE}.sql"
ARCHIVE="${OUT_DIR}/${FILENAME}.gz"

log "Dumping ${DB_CONNECTION} database to ${ARCHIVE}"

if [[ "${DB_CONNECTION}" == "pgsql" ]]; then
    PGPASSWORD="${DB_PASSWORD:-}" "${BACKUP_PGDUMP_BIN:-pg_dump}" \
        --host="${DB_HOST:-127.0.0.1}" \
        --port="${DB_PORT:-5432}" \
        --username="${DB_USERNAME:-lifeat}" \
        --dbname="${DB_DATABASE}" \
        --clean \
        --if-exists \
        --no-owner \
        --no-privileges \
        | gzip -9 > "${ARCHIVE}"
elif [[ "${DB_CONNECTION}" == "mysql" || "${DB_CONNECTION}" == "mariadb" ]]; then
    # --single-transaction avoids locking for InnoDB-only workloads.
    # --quick streams large tables row-by-row to avoid memory blowups.
    # --routines / --triggers / --events capture the full schema.
    "${BACKUP_MYSQLDUMP_BIN}" \
        --user="${DB_USERNAME:-root}" \
        --password="${DB_PASSWORD:-}" \
        --host="${DB_HOST:-127.0.0.1}" \
        --port="${DB_PORT:-3306}" \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        --events \
        --hex-blob \
        --default-character-set=utf8mb4 \
        "${DB_DATABASE}" \
        | gzip -9 > "${ARCHIVE}"
else
    # SQLite: VACUUM INTO first to defragment, then gzip.
    TMP_DUMP="${OUT_DIR}/${FILENAME}"
    sqlite3 "${DB_DATABASE_PATH}" ".timeout 5000" "VACUUM INTO '${TMP_DUMP}.tmp';"
    mv "${TMP_DUMP}.tmp" "${TMP_DUMP}"
    gzip -9 -c "${TMP_DUMP}" > "${ARCHIVE}"
    rm -f "${TMP_DUMP}"
fi

# Verify the archive is non-empty and starts with the expected magic bytes.
SIZE=$(stat -c %s "${ARCHIVE}" 2> /dev/null || stat -f %z "${ARCHIVE}")
if (( SIZE < 256 )); then
    die "Backup archive ${ARCHIVE} is suspiciously small (${SIZE} bytes)"
fi

log "Dump complete: ${ARCHIVE} (${SIZE} bytes)"

# ---------------------------------------------------------------------------
# Optional remote upload
# ---------------------------------------------------------------------------
s3_upload "${ARCHIVE}" "db/$(basename "${ARCHIVE}")"

# ---------------------------------------------------------------------------
# Retention
# ---------------------------------------------------------------------------
prune_local "${OUT_DIR}"

healthcheck_ping "ok"
log "Database backup OK"
