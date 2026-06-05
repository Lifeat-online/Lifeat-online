#!/usr/bin/env bash
# shellcheck shell=bash
#
# backup-storage.sh
# Archive the application's user-uploaded media (storage/app/public) into a
# single tarball, then (optionally) upload to S3-compatible storage.
#
# Usage:
#   scripts/backup/backup-storage.sh
#
# Cron entry (Hetzner):
#   0 3 * * 0  /opt/lifeat/scripts/backup/backup-storage.sh >> /var/log/lifeat/backup-storage.log 2>&1
#

set -Eeuo pipefail
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
# shellcheck source=_lib.sh
source "${SCRIPT_DIR}/_lib.sh"

DATE="$(date -u +%Y-%m-%d_%H%M%S)"
DAY="$(date -u +%Y-%m-%d)"
OUT_DIR="${BACKUP_LOCAL_PATH}/storage"
ensure_dir "${OUT_DIR}"

SOURCE_PATH="${BACKUP_STORAGE_SOURCE:-${PROJECT_ROOT}/storage/app/public}"
if [[ ! -d "${SOURCE_PATH}" ]]; then
    die "Backup source path does not exist: ${SOURCE_PATH}"
fi

ARCHIVE="${OUT_DIR}/lifeat-storage-${DAY}-${DATE}.tar.gz"
log "Archiving ${SOURCE_PATH} -> ${ARCHIVE}"

# --warning=no-file-changed keeps the output clean when nothing changed.
tar \
    --create \
    --gzip \
    --file="${ARCHIVE}" \
    --directory="$(dirname "${SOURCE_PATH}")" \
    --warning=no-file-changed \
    "$(basename "${SOURCE_PATH}")"

SIZE=$(stat -c %s "${ARCHIVE}" 2> /dev/null || stat -f %z "${ARCHIVE}")
if (( SIZE < 64 )); then
    die "Storage archive ${ARCHIVE} is suspiciously small (${SIZE} bytes)"
fi

log "Archive complete: ${ARCHIVE} (${SIZE} bytes)"

s3_upload "${ARCHIVE}" "storage/$(basename "${ARCHIVE}")"
prune_local "${OUT_DIR}"

healthcheck_ping "ok"
log "Storage backup OK"
