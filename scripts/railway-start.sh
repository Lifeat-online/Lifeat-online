#!/usr/bin/env bash
set -euo pipefail

if [ "${RAILWAY_SERVICE_NAME:-}" = "Reverb" ]; then
    exec php artisan reverb:start-railway --host=0.0.0.0 --port="${PORT:-8080}"
fi

if [ "${RAILWAY_SERVICE_NAME:-}" = "Lifeat-scheduler" ]; then
    exec php artisan schedule:work
fi

if [ "${RAILWAY_SERVICE_NAME:-}" = "Lifeat-worker" ]; then
    exec php artisan queue:work --sleep=3 --tries=3 --timeout=120
fi

php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan migrate --force

if command -v frankenphp >/dev/null 2>&1; then
    exec frankenphp run --config Caddyfile
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
