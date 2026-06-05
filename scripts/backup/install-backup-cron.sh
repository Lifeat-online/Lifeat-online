#!/usr/bin/env bash
# shellcheck shell=bash
#
# install-backup-cron.sh
# Install (or update) the crontab entries that drive the backup scripts.
#
# Usage:
#   sudo scripts/backup/install-backup-cron.sh            # install
#   sudo scripts/backup/install-backup-cron.sh --uninstall # remove
#

set -Eeuo pipefail
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
# shellcheck source=_lib.sh
source "${SCRIPT_DIR}/_lib.sh"

MODE="install"
if [[ "${1:-}" == "--uninstall" ]]; then
    MODE="uninstall"
fi

CRON_TAG="# lifeat-backup-managed"
CRON_TMP="$(mktemp -t lifeat-cron-XXXXXX)"
trap 'rm -f "${CRON_TMP}"' EXIT

# Load existing crontab (may be empty).
EXISTING="$(crontab -l 2> /dev/null || true)"
printf '%s\n' "${EXISTING}" > "${CRON_TMP}"

# Strip any prior managed block.
if [[ "${MODE}" == "install" ]]; then
    # Remove previous managed block (between markers).
    sed -i.bak "/${CRON_TAG}-begin/,/${CRON_TAG}-end/d" "${CRON_TMP}" || true
    rm -f "${CRON_TMP}.bak"

    cat >> "${CRON_TMP}" <<EOF
${CRON_TAG}-begin
${BACKUP_DB_SCHEDULE:-"0 2 * * *"}      ${SCRIPT_DIR}/backup-db.sh        >> /var/log/lifeat/backup-db.log 2>&1
${BACKUP_STORAGE_SCHEDULE:-"0 3 * * 0"}  ${SCRIPT_DIR}/backup-storage.sh   >> /var/log/lifeat/backup-storage.log 2>&1
${BACKUP_PRUNE_SCHEDULE:-"15 4 * * *"}   ${SCRIPT_DIR}/rotate-backups.sh   >> /var/log/lifeat/rotate.log 2>&1
${CRON_TAG}-end
EOF

    ensure_dir /var/log/lifeat

    log "Installing crontab from ${SCRIPT_DIR}"
    crontab "${CRON_TMP}"
    log "Crontab installed. Use 'crontab -l' to verify."
else
    sed -i.bak "/${CRON_TAG}-begin/,/${CRON_TAG}-end/d" "${CRON_TMP}" || true
    rm -f "${CRON_TMP}.bak"
    crontab "${CRON_TMP}"
    log "Crontab entries removed."
fi
