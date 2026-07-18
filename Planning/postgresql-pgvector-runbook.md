# Life@ PostgreSQL and pgvector clean deployment

Life@ uses a clean PostgreSQL 17 database with pgvector 0.8.2. Existing SQLite data is disposable. There is no import, dual-write, migration rehearsal, or data reconciliation step.

## Coolify database service

1. Create a persistent database service from `pgvector/pgvector:0.8.2-pg17`, or use `deploy/postgresql/compose.yaml`.
2. Set unique administrator and restricted application credentials. Never give the Laravel credential superuser, database-owner, `CREATEDB`, `CREATEROLE`, or extension-management privileges.
3. Run `deploy/postgresql/init-lifeat.sh` once through the database administrator context. It creates `vector`, verifies version `0.8.2`, and grants the application role only connection plus schema object creation rights.
4. Configure the web, queue worker, and scheduler with the same application-role values:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=<Coolify internal PostgreSQL host>
DB_PORT=5432
DB_DATABASE=lifeat
DB_USERNAME=lifeat_app
DB_PASSWORD=<application role password>
DB_SSLMODE=prefer
```

5. Deploy with `RUN_MIGRATIONS=true`. The knowledge migration fails clearly if `vector` is absent or is not version `0.8.2`.
6. Run reference/development seeders required for this non-production site, then `php artisan life:ai:validate-config`, `php artisan life:knowledge:reindex`, and `php artisan life:knowledge:audit --fail`.

## Verification

- `SELECT extversion FROM pg_extension WHERE extname = 'vector';` returns `0.8.2`.
- `SELECT rolsuper, rolcreatedb, rolcreaterole FROM pg_roles WHERE rolname = 'lifeat_app';` returns false for every privilege.
- `php artisan migrate:fresh --seed --force` succeeds against the empty database.
- Login, roles, public pages, admin pages, queue, scheduler, `/up`, and `/health` respond as expected.
- `scripts/backup/backup-db.sh` produces a non-empty gzip archive using `pg_dump`; `restore-db.sh` is available when a reset is explicitly needed, but no rehearsal is required.

Keep Ask Life anonymous access, editorial evidence writing, Operator Assistant, and operator mutations disabled until their independent application/database settings are deliberately enabled.
