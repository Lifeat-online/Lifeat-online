# Hetzner Backups

Production backups for the Lifeat platform. Two pieces:

1. **Shell scripts** in `scripts/backup/` — run on the Hetzner VPS via cron.
2. **Laravel commands** — `php artisan backup:run`, `backup:list`, `backup:restore` —
   thin wrappers around the shell scripts that you can run from a Coolify
   one-off container or invoke from the scheduler.

## What gets backed up

| What            | Where                                    | Schedule (default) |
|-----------------|------------------------------------------|--------------------|
| MySQL database  | `storage/app/backups/db/*.sql.gz`        | Daily 02:00 UTC    |
| User uploads    | `storage/app/backups/storage/*.tar.gz`   | Weekly Sun 03:00 UTC |
| Old archives    | pruned after 14 days                     | run as part of backup |

## Quick start (Hetzner VPS)

```bash
# 1. Pull the repo on the VPS.
cd /opt/lifeat

# 2. Make the shell scripts executable.
chmod +x scripts/backup/*.sh

# 3. Verify the env values in `.env` (BACKUP_* keys).
#    The defaults work for local-only backups; no extra config needed.

# 4. Smoke-test: dry-run syntax + helpers.
bash scripts/backup/self-test.sh

# 5. Run a database backup manually.
sudo scripts/backup/backup-db.sh

# 6. List available backups.
php artisan backup:list
php artisan backup:list --type=storage

# 7. Install the cron schedule.
sudo scripts/backup/install-backup-cron.sh
crontab -l   # verify
```

## Optional: Hetzner Storage Box (S3-compatible)

The Hetzner Storage Box exposes an S3-compatible endpoint using path-style
URLs. To turn on off-site backup:

```env
BACKUP_S3_ENABLED=true
BACKUP_S3_BUCKET=lifeat-backups
BACKUP_S3_PREFIX=production
BACKUP_S3_ENDPOINT=https://<username>.<box>.your-storagebox.de
BACKUP_S3_REGION=us-east-1
BACKUP_S3_PATH_STYLE=true
BACKUP_S3_ACCESS_KEY=<storage-box-key>
BACKUP_S3_SECRET_KEY=<storage-box-secret>
```

Install the AWS CLI on the VPS:

```bash
sudo apt update && sudo apt install -y awscli
```

Re-run `bash scripts/backup/self-test.sh` after editing `.env` — the script
verifies the cron expressions and helper symbols but does not require
credentials for the dry-run.

## Restoring a database

```bash
# Local archive
sudo scripts/backup/restore-db.sh /opt/lifeat/storage/app/backups/db/lifeat-2026-06-05_020000.sql.gz --yes

# Archive stored on the Storage Box
sudo scripts/backup/restore-db.sh /tmp/x.sql.gz --from-s3 db/lifeat-2026-06-05_020000.sql.gz --yes

# Or via the Laravel wrapper
php artisan backup:restore lifeat-2026-06-05_020000.sql.gz --yes
```

The restore script drops and recreates the target database. For SQLite
backups, it stashes the previous file next to the live database as
`database.sqlite.pre-restore-<timestamp>`.

## Healthchecks.io

Set `BACKUP_HEALTHCHECK_URL=https://hc-ping.com/<uuid>` and every script
will ping `/fail` on error and the bare URL on success. Combine with a
Hetzner uptime check (TCP 443 on the public IP) for a two-layer monitor.

## Logrotate

Deploy `deploy/hetzner/logrotate-lifeat` to `/etc/logrotate.d/lifeat`
and the daily cron will keep 30 days of compressed backup logs under
`/var/log/lifeat/`.
