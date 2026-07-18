#!/usr/bin/env bash
set -Eeuo pipefail

: "${POSTGRES_DB:?POSTGRES_DB is required}"
: "${POSTGRES_USER:?POSTGRES_USER is required}"
: "${LIFEAT_DB_APP_USER:?LIFEAT_DB_APP_USER is required}"
: "${LIFEAT_DB_APP_PASSWORD:?LIFEAT_DB_APP_PASSWORD is required}"

psql --set ON_ERROR_STOP=on --username "${POSTGRES_USER}" --dbname "${POSTGRES_DB}" \
    --set app_user="${LIFEAT_DB_APP_USER}" --set app_password="${LIFEAT_DB_APP_PASSWORD}" <<'SQL'
CREATE EXTENSION IF NOT EXISTS vector;

SELECT format('CREATE ROLE %I LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT', :'app_user', :'app_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app_user') \gexec

SELECT format('ALTER ROLE %I PASSWORD %L', :'app_user', :'app_password') \gexec
SELECT format('GRANT CONNECT ON DATABASE %I TO %I', current_database(), :'app_user') \gexec
SELECT format('GRANT USAGE, CREATE ON SCHEMA public TO %I', :'app_user') \gexec
SELECT format('GRANT USAGE ON TYPE vector TO %I', :'app_user') \gexec
SQL

installed_version="$(psql --tuples-only --no-align --username "${POSTGRES_USER}" --dbname "${POSTGRES_DB}" --command "SELECT extversion FROM pg_extension WHERE extname = 'vector'")"
if [[ "${installed_version}" != "0.8.2" ]]; then
    echo "Expected pgvector 0.8.2, found ${installed_version:-missing}." >&2
    exit 1
fi
