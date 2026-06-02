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
- **Database**: SQLite for local development, MySQL/PostgreSQL compatible for production
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
- Configure the Coolify health check path as `/up` with expected status `200`; the Nixpacks image includes `curl` so Coolify can run the probe inside the container.
- Run separate worker and scheduler processes for `php artisan queue:work --sleep=3 --tries=3 --timeout=120` and `php artisan schedule:work`, or enable them in the web container with `QUEUE_WORKER_ENABLED=true` and `SCHEDULER_ENABLED=true`.
- Auto-translation jobs default to the normal queue. For higher publishing volume, set `AUTO_TRANSLATION_QUEUE=translations` and run/document a worker that listens to it, for example `php artisan queue:work --queue=translations --sleep=3 --tries=2 --timeout=180`.
- Transport realtime uses Laravel Reverb. Run it online as a separate service/process with `php artisan reverb:start --host=0.0.0.0 --port=$PORT`, set `BROADCAST_CONNECTION=reverb`, and point `REVERB_HOST` / `VITE_REVERB_HOST` at that service's public HTTPS domain.
- For uploads, mount durable Hetzner/Coolify storage at `/app/storage/app` and set `UPLOAD_STORAGE_BACKEND=mounted_volume` plus `UPLOAD_STORAGE_MOUNT_PATH=/app/storage/app`; S3-compatible object storage can be wired later.
- Enable managed database backups and record a successful restore drill with `BACKUPS_ENABLED=true`, `BACKUP_PROVIDER`, `BACKUP_RESTORE_DRILL_COMPLETED=true`, and `BACKUP_LAST_RESTORE_DRILL_DATE`.
- Use the production readiness tracker in `Planning/production-readiness-todo.md` for launch blockers, verification, and operational hardening.

## License

Open-source software licensed under the MIT license.
