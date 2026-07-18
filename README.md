# Life Platform

A Laravel platform for local job creation and community monetisation. The app combines paid local writing, business directory packages, staff-assisted sales, events, advertising, push campaigns, vouchers, classifieds, and civic/community workflows.

## Key Features

- **Job creation engine**: Writer applications, article workflow, word-count ledgers, and writer payment batches.
- **Business directory**: Paid listings with owner and staff-assisted capture flows.
- **Advertising revenue stack**: Advert campaigns, push campaigns, package entitlements, tracking counters, and admin approval flows.
- **Commerce operations**: Checkout, PayFast payment attempts, invoices, subscriptions, renewals, refunds, and finance dashboards.
- **Staff wallets**: Staff-attributed sales, commission ledger entries, payout requests, and admin payout processing.
- **Community surfaces**: Events, articles, classifieds, vouchers, search, maps, and civic fault reporting.

## Tech Stack

- **Backend**: Laravel 13.x on PHP 8.4+
- **Frontend**: Blade, Tailwind CSS, Alpine.js, Vite
- **Database**: PostgreSQL 17 with pgvector 0.8.2 for application environments; in-memory SQLite for automated tests
- **Deployment**: Hetzner/Coolify Nixpacks deployment pipeline
- **Payments**: PayFast integration foundations

## Local Installation

1. Install dependencies:

   ```bash
   composer install
   npm install
   ```

2. Configure the environment:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Prepare the database:

   ```bash
   php artisan migrate --seed
   ```

   Default seeding loads production-safe reference data only. For local/browser QA demo accounts or the mall demo store, set `SEED_DEMO_USERS=true` or `MALL_SEED_DEMO=true` in a local/testing environment before running `db:seed`.

4. Run the app locally:

   ```bash
   php artisan serve
   npm run dev
   ```

For local PHPUnit runs, ensure the PHP CLI has these extensions enabled: `mbstring`, `openssl`, `fileinfo`, `gd`, `pdo_sqlite`, and `sqlite3`.

## Production Notes

- Hetzner/Coolify is responsible for deployment and release orchestration.
- The in-app git update utility has been removed; do not reintroduce app-level `git pull` deployment controls.
- Run `php artisan production:check` during deploy validation and resolve any errors before public launch.
- The `Release Readiness` GitHub Actions workflow runs PHP tests, Laravel route/cache/view smoke checks, frontend build, and Composer/npm dependency audits on pull requests, pushes to `master`/`main`, and manual dispatch.
- Configure the Coolify health check path as `/up` with expected status `200`; use `/health` or `php artisan monitoring:health` for richer database, storage, disk, queue, payment, and notification degradation signals.
- Critical payment, callback, subscription, campaign dispatch, voucher, and finance flows emit redacted `lifeat.operational.*` structured log events. Configure production log retention/shipping around those events before public launch.
- Enable `ERROR_TRACKING_ENABLED=true` with a real external `ERROR_TRACKING_WEBHOOK_URL` before public launch; the fallback log driver is useful locally but should not be the only production alerting path.
- Public read-model caches cover settings, active package catalogues, category/tag/location filters, public stats, and popular listing/event locations. Tune `LIFEAT_SETTINGS_CACHE_TTL`, `LIFEAT_CATALOG_CACHE_TTL`, and `LIFEAT_PUBLIC_CACHE_TTL` after production load evidence.
- Run separate worker and scheduler processes for `php artisan queue:work --sleep=3 --tries=3 --timeout=120` and `php artisan schedule:work`, or enable them in the web container with `QUEUE_WORKER_ENABLED=true` and `SCHEDULER_ENABLED=true`.
- Auto-translation jobs default to the normal queue. For higher publishing volume, set `AUTO_TRANSLATION_QUEUE=translations` and run/document a worker that listens to it, for example `php artisan queue:work --queue=translations --sleep=3 --tries=2 --timeout=180`.
- Transport realtime uses Laravel Reverb. Run it online as a separate service/process with `php artisan reverb:start --host=0.0.0.0 --port=$PORT`, set `BROADCAST_CONNECTION=reverb`, and point `REVERB_HOST` / `VITE_REVERB_HOST` at that service's public HTTPS domain.
- For uploads, mount durable Hetzner/Coolify storage at `/app/storage/app` and set `UPLOAD_STORAGE_BACKEND=mounted_volume` plus `UPLOAD_STORAGE_MOUNT_PATH=/app/storage/app`; S3-compatible object storage can be wired later.
- Enable PostgreSQL backups with `BACKUPS_ENABLED=true` and document `BACKUP_PROVIDER`; this non-production clean reset does not require a restore rehearsal.
- Provision the first admin from the deployment shell with `php artisan admin:create`; the command writes an audit log for account creation or promotion.
- A pre-push hook lives in `.githooks/pre-push` (run `./setup-hooks.sh` once per clone) and runs `vendor/bin/pint --test` plus a smoke subset of `php artisan test` (filter `BackupCommand|OperatorPushNotifier|ErrorTracking`) before allowing a push. Skip with `SKIP_PRE_PUSH=1 git push …`.
- Use the production readiness tracker in `Planning/production-readiness-todo.md` for launch blockers, verification, and operational hardening.

## License

Open-source software licensed under the MIT license.
