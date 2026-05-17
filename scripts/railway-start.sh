#!/usr/bin/env bash
set -euo pipefail

if [ "${RAILWAY_SERVICE_NAME:-}" = "Reverb" ]; then
    exec php artisan reverb:start-railway --host=0.0.0.0 --port="${PORT:-8080}"
fi

php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan migrate --force

if command -v frankenphp >/dev/null 2>&1; then
    exec frankenphp run --config Caddyfile
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
