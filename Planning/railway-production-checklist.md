# Railway Production Checklist

Use this checklist before a production launch or major Railway redeploy.

## Application Environment

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` points to the canonical HTTPS production domain.
- `APP_KEY` is set and backed up securely.
- `APP_PREVIOUS_KEYS` is configured before any key rotation.
- `LOG_CHANNEL` and log retention are appropriate for Railway.
- Trusted proxy handling is confirmed for Railway ingress.
- `php artisan production:check` passes, or each reported warning has a documented launch decision.

## Database

- Production database service is provisioned and connected.
- `DB_CONNECTION`, host, port, database, username, and password are set in Railway variables.
- Migrations have been tested against a production-like database.
- Automated backups are enabled in Railway or the chosen managed database provider.
- `BACKUPS_ENABLED=true`, `BACKUP_PROVIDER`, and `BACKUP_RETENTION_DAYS` are set in Railway variables.
- A restore drill has been completed against a non-production database, then documented with `BACKUP_RESTORE_DRILL_COMPLETED=true` and `BACKUP_LAST_RESTORE_DRILL_DATE=YYYY-MM-DD`.
- Long-running migration risk has been reviewed before release.

## Queues And Scheduler

- Queue driver is production-ready.
- `QUEUE_WORKER_ENABLED=true` is set only after a Railway worker service is configured.
- Worker service command: `php artisan queue:work --sleep=3 --tries=3 --timeout=120`.
- `QUEUE_WORKER_COMMAND` records the command used by the worker service.
- `SCHEDULER_ENABLED=true` is set only after a Railway scheduler service or cron is configured.
- Scheduler service command: `php artisan schedule:work`, or a Railway cron that runs `php artisan schedule:run` once per minute.
- `SCHEDULER_COMMAND` records the command used by the scheduler/cron service.
- Transport realtime is deployed as a separate Reverb service/process when taxi/delivery realtime is enabled.
- Reverb service command: `php artisan reverb:start --host=0.0.0.0 --port=$PORT`.
- App service variables include `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, and matching `VITE_REVERB_*` values before rebuilding frontend assets.
- Failed queue jobs are monitored.
- Mail, subscription reminders, expiry sweeps, and push dispatch jobs are covered.

## Storage And Uploads

- Public media storage is backed by durable object storage, not ephemeral container disk.
- Private compliance/application documents are stored outside public web access.
- `UPLOAD_STORAGE_BACKEND` is set to `railway_volume` when `storage/app` is mounted to a durable Railway volume.
- `UPLOAD_STORAGE_MOUNT_PATH` records the mounted upload volume path.
- If the future storage decision is S3-compatible object storage, refactor hardcoded `public` and `local` upload disk usage before setting `UPLOAD_STORAGE_BACKEND=s3`.
- `FILESYSTEM_DISK` and any S3-compatible credentials are set for the selected storage strategy.
- Upload limits match Railway/proxy/PHP limits.
- Orphan file cleanup policy is defined.
- `php artisan uploads:orphans --disk=public` and `php artisan uploads:orphans --disk=local` are reviewed before cleanup; use `--delete` only after confirming output.

## Payments

- PayFast merchant credentials are production credentials.
- Sandbox mode is disabled for production.
- PayFast callback URL uses HTTPS and points to `/checkout/payfast/callback`.
- Callback signature/passphrase settings are verified.
- Payment success, failure, retry, duplicate callback, and bad signature flows are tested.

## Mail And Notifications

- Mail provider credentials are configured.
- Sender domain is authenticated with SPF, DKIM, and DMARC.
- Invoice, renewal, writer access, and voucher emails are tested.
- Failed mail delivery is observable.

## Security

- In-app git update tooling is removed; Railway is the deployment authority.
- `/dev/tests/run` is disabled in production unless deliberately enabled with `DEV_TOOLS_ENABLED=true` and `DEV_TEST_RUNNER_ENABLED=true`.
- Admin bootstrap route has a production disable/removal decision.
- Session cookie security is reviewed.
- Rate limits are reviewed for auth, PayFast callbacks, voucher redemption, and public forms.
- Audit logging is reviewed for sensitive admin actions.

## Release

- `npm run build` passes.
- PHPUnit passes on PHP 8.4+.
- `php artisan route:list` is reviewed for unexpected public/admin routes.
- `php artisan production:check --fail-on-warning` is reviewed before final public launch.
- `php artisan config:cache`, `route:cache`, and `view:cache` are tested.
- Migration rollback strategy is documented.
- Railway deployment health is checked after release.
- Smoke tests cover home, directory, checkout, admin login, finance dashboard, and payment callback validation.
