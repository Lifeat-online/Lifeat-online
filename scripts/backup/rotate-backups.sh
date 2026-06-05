#!/usr/bin/env bash
# shellcheck shell=bash
#
# rotate-backups.sh
# Prune local archives older than BACKUP_RETENTION_DAYS and (optionally)
# delete the corresponding objects from the S3-compatible bucket.
#
# Usage:
#   scripts/backup/rotate-backups.sh
#
# Cron entry (Hetzner):
#   15 4 * * *  /opt/lifeat/scripts/backup/rotate-backups.sh >> /var/log/lifeat/rotate.log 2>&1
#

set -Eeuo pipefail
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
# shellcheck source=_lib.sh
source "${SCRIPT_DIR}/_lib.sh"

log "Pruning local backups older than ${BACKUP_RETENTION_DAYS} days"
prune_local "${BACKUP_LOCAL_PATH}/db"
prune_local "${BACKUP_LOCAL_PATH}/storage"

# ---------------------------------------------------------------------------
# Optional S3 pruning – walk the bucket prefix and remove objects older than
# the retention window. This is best-effort: if `aws` is not installed or
# credentials are missing we just log and exit.
# ---------------------------------------------------------------------------
if [[ "${BACKUP_S3_ENABLED}" != "true" || -z "${BACKUP_S3_BUCKET}" ]]; then
    log "S3 prune skipped (BACKUP_S3_ENABLED=${BACKUP_S3_ENABLED})."
    healthcheck_ping "ok"
    exit 0
fi

if ! command -v aws > /dev/null 2>&1; then
    warn "aws CLI not found – skipping remote prune"
    healthcheck_ping "ok"
    exit 0
fi

CUTOFF_EPOCH=$(($(date +%s) - BACKUP_RETENTION_DAYS * 86400))
CUTOFF_ISO=$(date -u -d "@${CUTOFF_EPOCH}" +%Y-%m-%dT%H:%M:%SZ 2> /dev/null || date -u -r "${CUTOFF_EPOCH}" +%Y-%m-%dT%H:%M:%SZ)
log "Pruning S3 objects under s3://${BACKUP_S3_BUCKET}/${BACKUP_S3_PREFIX}/ older than ${CUTOFF_ISO}"

endpoint_args=()
[[ -n "${BACKUP_S3_ENDPOINT}" ]] && endpoint_args+=(--endpoint-url "${BACKUP_S3_ENDPOINT}")

# Build the JMESPath query as a separate variable to avoid nested quoting.
QUERY='Contents[?LastModified<=`'${CUTOFF_ISO}'`].[Key]'

AWS_ACCESS_KEY_ID="${BACKUP_S3_ACCESS_KEY}" \
AWS_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET_KEY}" \
AWS_DEFAULT_REGION="${BACKUP_S3_REGION}" \
aws s3api list-objects-v2 \
    --bucket "${BACKUP_S3_BUCKET}" \
    --prefix "${BACKUP_S3_PREFIX}/" \
    "${endpoint_args[@]}" \
    --output json \
    --query "${QUERY}" \
    --region "${BACKUP_S3_REGION}" \
| jq -r '.[]? | .[]?' \
| while read -r key; do
    [[ -z "${key}" ]] && continue
    log "Deleting s3://${BACKUP_S3_BUCKET}/${key}"
    AWS_ACCESS_KEY_ID="${BACKUP_S3_ACCESS_KEY}" \
    AWS_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET_KEY}" \
    AWS_DEFAULT_REGION="${BACKUP_S3_REGION}" \
    aws s3 rm "s3://${BACKUP_S3_BUCKET}/${key}" --only-show-errors "${endpoint_args[@]}" --region "${BACKUP_S3_REGION}"
done

healthcheck_ping "ok"
log "Rotation OK"
