#!/usr/bin/env bash
set -euo pipefail
# Only runs for a fresh PostgreSQL volume. Secrets are never interpolated as SQL.
export TASK_RUNTIME_PASSWORD="$(cat /run/secrets/runtime_password)"
export TASK_MIGRATION_PASSWORD="$(cat /run/secrets/migration_password)"
psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --set ON_ERROR_STOP=1 <<'SQL'
\getenv runtime_password TASK_RUNTIME_PASSWORD
\getenv migration_password TASK_MIGRATION_PASSWORD
CREATE ROLE holyspace_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD :'runtime_password';
CREATE ROLE holyspace_migrator LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD :'migration_password';
REVOKE ALL ON DATABASE holyspace FROM PUBLIC;
GRANT CONNECT ON DATABASE holyspace TO holyspace_runtime, holyspace_migrator;
-- Required for the trusted btree_gist extension used by the People migration.
GRANT CREATE ON DATABASE holyspace TO holyspace_migrator;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
ALTER SCHEMA public OWNER TO holyspace_migrator;
GRANT USAGE ON SCHEMA public TO holyspace_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE holyspace_migrator IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO holyspace_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE holyspace_migrator IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO holyspace_runtime;
SQL
unset TASK_RUNTIME_PASSWORD TASK_MIGRATION_PASSWORD
