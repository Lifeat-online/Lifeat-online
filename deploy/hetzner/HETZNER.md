# Hetzner Runbook — lifeat.online

Live server operations, deploy, rollback, and incident response for the
Lifeat platform running on a Hetzner VPS via Coolify + Nixpacks + PHP-FPM.

> **Note on testing:** Localhost testing is not used. All changes ship
> through `git push origin master` and are auto-deployed by Coolify. The
> pre-flight check is `php artisan test` (run from the developer's
> machine against the test SQLite database) and `php artisan test
> --filter=<class>` on a touched file. Verification post-deploy is via
> `curl https://lifeat.online/up` and the routes listed below.

> **Note on alerts:** All operational alerts (backup failures, disk
> pressure, health-check failures, queue backlogs, deploy errors) are
> delivered to the developer via **Web Push** (VAPID-signed notifications
> to the browser, using the same Minishlink/WebPush pipeline that the
> app already ships for end-user push campaigns). Email and the daily
> log channel are fallbacks. Healthchecks.io remains a passive
> dead-man switch and is paired with a push delivery on every script
> exit. See section 7 for the wiring.

---

## 1. Topology

| Layer            | Component                                            |
|------------------|------------------------------------------------------|
| Reverse proxy    | nginx (Nixpacks static asset, port `${PORT}`)        |
| App server       | PHP 8.4 FPM (Nixpacks phase), 512 MB memory limit    |
| Queue workers    | `php artisan queue:work` (supervised, optional)      |
| Scheduler        | `php artisan schedule:work` (supervised, optional)   |
| Database         | SQLite at `${DB_DATABASE}` (default `/app/storage/database.sqlite`) |
| Session/cache    | Database driver (Laravel `database` session + `array` cache) |
| Reverb           | WebSocket server, optional worker                    |
| Web push         | Minishlink/WebPush with VAPID keys in `.env`         |
| Backups          | `scripts/backup/*.sh` cron, optional S3-compatible   |

Supervisor unit definitions live in `nixpacks.toml` (embedded as
`worker-*.conf` static assets) and are emitted to
`/etc/supervisor/conf.d/` at container start.

---

## 2. Deploy

### 2.1 Normal flow

```bash
# 1. Local — pre-flight
php artisan test --testsuite=Feature
vendor/bin/pint --test    # if installed

# 2. Commit + push (Coolify auto-deploys the master branch)
git push origin master

# 3. Watch the Coolify build log (or in-app supervisor):
#    php /app/artisan optimize
#    if [ "${RUN_MIGRATIONS:-true}" != "false" ]; then
#        php /app/artisan migrate --force
#    fi
#    supervisord -c /etc/supervisord.conf -n
```

### 2.2 Migrations

`RUN_MIGRATIONS` defaults to `true` in `nixpacks.toml` and runs on every
deploy. To deploy code-only (no schema changes), set
`RUN_MIGRATIONS=false` in Coolify for the release.

### 2.3 Asset builds

Nixpacks runs `npm ci` and `npm run build` during the build phase. The
output is committed in `public/build/` so the container boots without
rebuilding. To force a fresh asset build, clear the build cache in
Coolify.

---

## 3. Rollback

### 3.1 Application code

```bash
# On the VPS
cd /app
git fetch origin
git reset --hard <known-good-sha>     # e.g. 58b8672
php artisan optimize
supervisorctl restart all             # pick up code changes
```

### 3.2 Database

SQLite is a single file. Restore from a backup:

```bash
# List local backups
php artisan backup:list

# Restore the most recent
php artisan backup:restore lifeat-<date>.sql.gz --yes
```

For S3-stored backups, pass `--from-s3`:

```bash
php artisan backup:restore x.sql.gz \
    --from-s3 db/lifeat-2026-06-05_020000.sql.gz --yes
```

The restore script drops and recreates the target database.

### 3.3 Migrations

```bash
# Roll back the most recent batch
php artisan migrate:rollback --step=1

# Or roll back to a specific migration
php artisan migrate:rollback --path=/database/migrations/2026_xx_xx_xxxxxx.php
```

---

## 4. Health checks

### 4.1 From inside the container

```bash
# Application HTTP
curl -fsS -o /dev/null -w "%{http_code}\n" https://lifeat.online/up

# Laravel health (DB + cache + queue + failed_jobs)
php artisan monitoring:health --json

# Production readiness (env + keys + S3 + PayFast + push + …)
php artisan production:check --fail-on-warning
```

### 4.2 From outside (Healthchecks.io / UptimeRobot)

- `https://lifeat.online/up` → 200 + green pulse (1 s)
- `https://lifeat.online/` → 200 + HSTS header
- `https://lifeat.online/sitemap.xml` → 200 + valid XML

### 4.3 Common HTTP signals

| Signal                                  | Likely cause                              |
|----------------------------------------|-------------------------------------------|
| `/up` returns 500                       | DB unreachable / corrupted SQLite        |
| `/up` returns 200 but routes 500        | Cache driver issue / session driver issue |
| `/up` green but `/admin` 500            | Storage symlink broken                    |
| HSTS header missing on `/`              | `APP_FORCE_HTTPS` not set or middleware  |
| `__bootstrap/admin` 200                 | **Re-add the removed route immediately**  |

---

## 5. Logs

| Log file (inside container)              | Purpose                          |
|-----------------------------------------|----------------------------------|
| `/var/log/nginx-access.log`              | HTTP access                      |
| `/var/log/nginx-error.log`               | HTTP errors                      |
| `/var/log/worker-nginx.log`              | nginx supervisor wrapper         |
| `/var/log/worker-phpfpm.log`             | PHP-FPM stdout/stderr            |
| `/var/log/worker-laravel.log`            | Queue worker                     |
| `/var/log/worker-scheduler.log`          | Scheduler worker                 |
| `/var/log/supervisord.log`               | Supervisor master log            |
| `storage/logs/laravel-*.log`             | Laravel app log (daily channel)  |
| `storage/logs/lifeat-*.log`              | Structured app log               |

Production uses `LOG_STACK=daily` (configure in `.env`). The daily
channel rotates at midnight UTC and keeps `LOG_DAILY_DAYS=14` days by
default. See `deploy/hetzner/logrotate-lifeat` for the OS-level
logrotate config.

To tail logs from the host:

```bash
docker exec -it <container> tail -f storage/logs/laravel-$(date -u +%Y-%m-%d).log
```

---

## 6. Backups

Daily DB dump and weekly storage archive. See `deploy/hetzner/BACKUPS.md`
for the full pipeline. The TL;DR:

```bash
# Manual
php artisan backup:run                # all
php artisan backup:run --type=db      # DB only
php artisan backup:run --no-upload    # local only

# List / restore
php artisan backup:list
php artisan backup:restore <name>.sql.gz --yes

# Cron (auto-installed via scripts/backup/install-backup-cron.sh)
#   02:00 daily  -> backup-db
#   03:00 weekly -> backup-storage
# Pruning is part of the backup step.
```

Retention is `BACKUP_RETENTION_DAYS=14` by default. The script pings
`BACKUP_HEALTHCHECK_URL` with `/fail` on error and the bare URL on
success. The wrapper `App\Console\Commands\BackupCommand` also invokes
`OperatorPushNotifier` on every non-zero exit so the developer
receives a Web Push notification immediately (within seconds of the
cron tick).

---

## 7. Incident response

### 7.0 Alerts & push notifications

Operational alerts (failed backups, health-check failures, disk
pressure, queue backlogs, PayFast ITN failures, deploy errors) are
delivered to the developer via **Web Push** using the same
Minishlink/WebPush pipeline that powers end-user push campaigns.

- Channel primary: Web Push (browser notification, OS-level, no app
  required)
- Channel fallback: email via `MAIL_*` env (low-priority daily digest)
- Channel passive: Healthchecks.io `/fail` ping for dead-man's switch
- Channel log: `LOG_CHANNEL=stack` writes to `storage/logs/laravel-*.log`

The Laravel-side glue lives in `App\Services\OperatorPushNotifier`
(registered as a singleton, auto-resolved by the container). It pulls
the VAPID keys from `WEBPUSH_VAPID_PUBLIC_KEY` /
`WEBPUSH_VAPID_PRIVATE_KEY` and sends to all active
`BrowserPushSubscription` records whose `user_id` is in the operator
roster (configured via `OPS_ALERT_USER_IDS` env, comma-separated).

```php
// Anywhere in app code
app(\App\Services\OperatorPushNotifier::class)->send(
    title: 'Backup failed',
    body:  'lifeat-db 2026-06-05_020000 returned exit 1',
    severity: 'critical',          // critical | warning | info
    url:    'https://lifeat.online/admin/finance',
);
```

**Triage rule:** a `critical` push must be acknowledged within 30
minutes. If no acknowledgment, the scheduler re-sends every 15
minutes (capped at 4 re-sends per alert fingerprint).

#### 7.0.1 Wiring at a glance

| Alert source                       | Trigger                              | Push target            |
|-----------------------------------|--------------------------------------|------------------------|
| `scripts/backup/*.sh` (all)       | exit code != 0                       | `backup:failed`        |
| `php artisan monitoring:health`   | report status in {degraded, down}    | `monitoring:degraded`  |
| `php artisan production:check`    | any error-level finding              | `production:check:err` |
| Cron `ops:check-disk`              | usage >= 80%                         | `disk:warning`         |
| Cron `ops:check-disk`              | usage >= 95%                         | `disk:critical`        |
| Cron `ops:check-queue-depth`       | depth > 500 jobs in default queue    | `queue:backlog`        |
| Deploy pipeline (Coolify)          | container start fails                | `deploy:failed`        |
| `php artisan backup:prune`        | pruning removes > 50 archives        | `backup:prune:large`   |

#### 7.0.2 Where the data lives

- Web Push subscriptions: `browser_push_subscriptions` table
- Operator roster: `.env` → `OPS_ALERT_USER_IDS=1,42`
- VAPID keys: generated via `php artisan webpush:keys`
- Last alert fingerprint / acknowledgment: `operator_alert_states`
  table (one row per `(user_id, fingerprint)`)

#### 7.0.3 Testing

```bash
# Dry-run a push to the operator roster
php artisan ops:send-test-push --user=1

# Tail the structured log for alert events
tail -f storage/logs/lifeat-*.log | grep -i operator.alert
```

See `PUSH-ALERTS.md` for the full operator-facing spec.

### 7.1 The 5-minute triage

```bash
# 1. Is the app up?
curl -fsS -o /dev/null -w "%{http_code}\n" https://lifeat.online/up

# 2. Are all workers alive?
supervisorctl status

# 3. Recent errors?
tail -n 200 storage/logs/laravel-$(date -u +%Y-%m-%d).log | grep -i 'error\|exception'

# 4. Disk space?
df -h /app

# 5. Database integrity?
php -r "new PDO('sqlite:' . getenv('DB_DATABASE')); echo 'OK';" \
    || echo "DB unreachable"
```

### 7.2 Common incidents

**Reverb not connecting**
- Check `REVERB_SERVER_HOST`, `REVERB_PORT`, `REVERB_HOST` match
  what nginx proxies.
- Inspect `storage/logs/lifeat-*.log` for `pusher:connection`
  errors.

**PayFast ITN not processing**
- Verify `MALL_PAYFAST_VALIDATE_ITN_WITH_SERVER=true` in `.env`.
- Tail `storage/logs/laravel-*.log` for `payfast` and check the
  NotificationLog table for failed attempts.

**Slow admin dashboard**
- `php artisan cache:clear` if PublicReadCache gets stuck.
- Confirm `pr_*` indexes exist (migration
  `2026_06_05_000001_add_production_readiness_indexes`).
- Check `php artisan monitoring:health` for queue depth.

**Storage full**
- `du -sh storage/app/public/* | sort -h | tail`
- Move the largest directory into a Hetzner Storage Box mount.
- Bump `BACKUP_S3_BUCKET` to offload to remote.

**Migration that broke a column**
- `php artisan migrate:rollback --step=1` to undo the last batch.
- Edit the migration file or add a follow-up migration; do not edit
  applied migrations in place.

### 7.3 After any incident

1. Capture: exact command, time, error message, relevant log lines.
2. File a post-mortem in `Planning/incident-postmortems/<date>-<slug>.md`.
3. Open a follow-up commit with a regression test (or runbook update).

---

## 8. On-call checklist

- [ ] Backup cron ran in the last 24 h (`backup:list` non-empty)
- [ ] Healthchecks.io ping is green
- [ ] **No unacknowledged critical push alerts** in `operator_alert_states`
      (`php artisan ops:list-alerts --severity=critical`)
- [ ] `/up` is green from external monitor
- [ ] `php artisan production:check` has 0 errors
- [ ] Queue depth < 100 (`php artisan queue:monitor database:default --max=100`)
- [ ] Disk usage < 80% on `/app`
- [ ] SSL certificate auto-renews (`/var/log/letsencrypt/letsencrypt.log`)

> If a `critical` push arrives, follow section 7.1 first, then open a
> post-mortem per section 7.3.

---

## 9. Useful one-liners

```bash
# Who am I logged in as on the VPS?
whoami && uname -a

# What's the current commit?
git -C /app rev-parse --short HEAD

# What workers are running?
supervisorctl status

# Most recent failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Clear all caches (careful on prod)
php artisan optimize:clear

# Re-prime caches
php artisan optimize

# Reset the Reverb restart signal
php artisan reverb:restart
```

---

## 10. Environment variables (production)

| Var                                      | Default                          |
|------------------------------------------|----------------------------------|
| `APP_ENV`                                | `production`                     |
| `APP_DEBUG`                              | `false` (enforced by middleware) |
| `APP_URL`                                | `https://lifeat.online`          |
| `APP_FORCE_HTTPS`                        | `true`                           |
| `DB_CONNECTION`                          | `sqlite`                         |
| `LOG_STACK`                              | `daily`                          |
| `LOG_DAILY_DAYS`                         | `14`                             |
| `RUN_MIGRATIONS`                         | `true`                           |
| `QUEUE_WORKER_ENABLED`                   | `false`                          |
| `SCHEDULER_ENABLED`                      | `false`                          |
| `BACKUP_S3_BUCKET`                       | *(empty, local-only)*            |
| `BACKUP_HEALTHCHECK_URL`                 | *(empty, no pings)*              |
| `ERROR_TRACKING_ENABLED`                 | `false`                          |
| `ERROR_TRACKING_WEBHOOK_URL`             | *(empty)*                        |
| `MALL_PAYFAST_VALIDATE_ITN_WITH_SERVER`  | `false` (must be `true` in prod) |
| `REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS`     | `120` per minute                 |
| `VAPID keys (WEBPUSH_*)`                 | set via `webpush:keys` artisan   |

See `.env.example` for the full list and per-flag documentation.
