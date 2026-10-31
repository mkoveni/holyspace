# PostgreSQL and field-level audit operations

## Implemented behavior

PostgreSQL 18 has its own baseline in `backend/migrations-postgresql`; the existing MySQL migration history stays in `backend/migrations`. Select the path with `MIGRATIONS_PATH`. Never run the PostgreSQL baseline over an existing MySQL volume or mark the MySQL migration versions as executed in a PostgreSQL database.

The baseline uses native UUIDs, JSONB documents and UTC timestamps, while keeping people, families, relationships, events, money, registration queues and foreign keys relational. Document tables have `payload_schema_version = 1`, separate from optimistic record versions. Changing the supported document schema requires a migration and mapper changes. The communications probe uses an indexed JSONB delivery-status expression.

Every business table has INSERT/UPDATE/DELETE audit triggers. Authoritative history lives in `audit_entries`; `workflow_audit` and `account_security_audit` remain legacy/domain metadata journals. Outbox delivery bookkeeping, payment-notification deduplication and lock tables are operational data, not a second business history. Portal writes audit the owning business tables with source `member_portal`.

Audit entries include record/table/context, action, versions where available, actor ID, source, request ID, transaction ID, UTC time and JSONB field changes. JSON Pointer paths escape `/` and `~`. Presence flags distinguish missing keys from null. Arrays are compared as whole values. Secret fields, tokens, password hashes, recovery values, image bytes, message content, form answers, support notes and pickup labels are redacted. Redacted fields still record that a change occurred. Existing historical before-values cannot be recovered from legacy journals.

DBAL middleware sets attribution transaction-locally, including writes outside application transaction wrappers. It resets identity on each operation and uses UTC without leaving request identity in pooled sessions. It does not hold a workflow lock beyond its intended transaction or commit an application's outer transaction. Direct SQL outside DBAL still triggers an atomic audit entry, with source `system` unless attribution was supplied explicitly.

Audit failures abort the business write. UPDATE, DELETE and TRUNCATE on authoritative history are blocked; TRUNCATE on business tables is blocked because it cannot supply per-row before-values. Production uses separate migration and runtime database roles. A database owner/superuser can change triggers, so those credentials must stay out of web/worker environments. Retention/archive deletion requires a separately reviewed administrative procedure; automatic deletion is not enabled.

## Development stack

```sh
docker compose up -d --build database php
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose up -d
```

PostgreSQL is the default development database in `docker-compose.yaml`. The previous `compose.postgresql.yaml` remains an empty compatibility override for older commands. Set `POSTGRES_PASSWORD` to change the local database password; if it contains reserved URL characters, also set `POSTGRES_PASSWORD_URLENCODED` to its percent-encoded value. The API and workers share the same connection, PostgreSQL migration path and advisory locks. PostgreSQL uses a separate `postgres_data` volume; existing MySQL volumes are preserved and their data is not imported automatically. See the transfer procedure below to import an existing installation. The development database role owns its schema for convenience; use the split roles below for production.

pgAdmin is available at `http://localhost:5050` (defaults: `admin@example.com` / `pgadmin_password`, configurable through `PGADMIN_DEFAULT_EMAIL` and `PGADMIN_DEFAULT_PASSWORD`). Register the server using host `database`, port `5432`, database `church_portal`, user `church` and the PostgreSQL password.

The final PostgreSQL migration seeds the `administrator` role with every permission and updates existing administrator roles. It creates no user account or default password; use the administrator bootstrap command to create the first account. Future permission migrations must also grant their new permissions to administrator roles.

## Production bootstrap and rehearsal

`compose.production.postgresql.yaml` is a standalone production stack, not an override of the MySQL file. Copy `.env.production.postgresql.example` to the ignored `.env.production.postgresql.local`. Use separate runtime and migration passwords; `DATABASE_PASSWORD_URLENCODED` is the percent-encoded runtime password. Set JWT file paths and the other required settings as in the existing production runbook.

Use these arguments for PostgreSQL production commands:

```sh
docker compose --env-file .env.production.postgresql.local -f compose.production.postgresql.yaml up -d database
```

Privately export `MIGRATION_DATABASE_URL` using user `church_migrator`, the percent-encoded migration password, hostname `database`, port `5432`, database `church_portal` and `serverVersion=18`. Do not put credentials in version control. Build the application and run migrations using that connection:

```sh
docker compose --env-file .env.production.postgresql.local -f compose.production.postgresql.yaml build php tools
docker compose --env-file .env.production.postgresql.local -f compose.production.postgresql.yaml run --rm --no-deps -e DATABASE_URL="$MIGRATION_DATABASE_URL" php php bin/console doctrine:migrations:migrate --no-interaction
```

Open a database administrator session and create the separate runtime role:

```sh
docker compose --env-file .env.production.postgresql.local -f compose.production.postgresql.yaml exec database psql -U church_migrator -d church_portal
```

Inside psql run `CREATE ROLE church LOGIN;`, then `\password church` and enter the runtime password. Do not grant owner, superuser, schema-create or role-management privileges. Then apply the application grants:

```sh
docker compose --env-file .env.production.postgresql.local -f compose.production.postgresql.yaml run --rm --no-deps -e DATABASE_URL="$MIGRATION_DATABASE_URL" php php bin/console app:database:grant-runtime church
```

The grant command provides business DML, read-only authoritative history, and the required outbox sequence access. It rejects a database-owner or privileged role. Reapply after migrations that add tables. Advisory locks use dedicated database connections; use direct connections or session pooling rather than transaction-mode PgBouncer pooling for the lock DSN.

## Existing data transfer

Rehearse against a fresh PostgreSQL database and a read-only MySQL snapshot before scheduling a cutover. Apply all existing MySQL migrations to the source first. Match the schema through `Version20261012070000`. Confirm legacy timestamps represent UTC; the transfer treats timezone-less values as UTC. Invalid UUIDs, unsupported JSONB values (including `\u0000`), missing required fields and constraint violations abort the target transaction.

For a transfer, initialize the fresh PostgreSQL target only through `DoctrineMigrations\Version20261021020000` (pass this version to `doctrine:migrations:migrate`), so the audited schema exists before administrator seeding. Run migrations to latest after the verified import.

Put the source application and all workers into maintenance before the final transfer. Export `MYSQL_SOURCE_URL` privately using a read-only source login reachable from the migration container. Do not pass a live writable source into an improvised dual-write workflow.

```sh
docker compose --env-file .env.production.postgresql.local -f compose.production.postgresql.yaml run --rm --no-deps -e DATABASE_URL="$MIGRATION_DATABASE_URL" -e MYSQL_SOURCE_URL php php bin/console app:database:import-mysql
```

The importer refuses a target with business records or existing authoritative audit history. It takes a consistent read-only MySQL snapshot, locks target business tables, transfers in foreign-key order and verifies per-table counts and canonical SHA-256 checksums before committing. It preserves JSON object/array distinctions, binary images, legacy history, record versions and outbox positions. It resets the outbox sequence and marks new field history as `import`; it does not invent pre-import field history. Bootstrap permission IDs are replaced with source IDs so existing assignments remain valid. Failure rolls back the entire target copy, including new audit entries; source data is never modified.

Save the verification output with the release record. Compare financial totals, memberships and image checksums, smoke-test the portal and workers, then resume traffic against PostgreSQL. Keep the source and a verified encrypted backup intact through the rollback window. Once PostgreSQL accepts new writes, rollback requires explicit reconciliation rather than pointing users at a stale MySQL database. No production cutover is performed by installing these files.

## Backups and restore rehearsal

Build the `tools` image above. PostgreSQL-specific scripts use the PostgreSQL compose file and `.env.production.postgresql.local` by default:

```sh
scripts/production/backup-postgresql.sh AGE_PUBLIC_RECIPIENT /secure/backups
scripts/production/restore-drill-postgresql.sh /secure/backups/backup.dump.age /secure/age-identity
```

Backups use PostgreSQL custom format, encrypted with age; failed dumps never become successful backup files. The restore drill uses a disposable PostgreSQL container without published ports or network access, restores with error checking, reports record counts and verifies audit immutability. Compare counts and checksums with the release record and run an application smoke test before a real recovery. Restore migration ownership and reapply runtime grants after recovery. Existing MySQL backup scripts remain separate.

To use the shared operational check script with PostgreSQL:

```sh
PRODUCTION_ENV_FILE=.env.production.postgresql.local PRODUCTION_COMPOSE_FILE=compose.production.postgresql.yaml scripts/production/check.sh
```

Readiness fails when audit history, trigger coverage or the document schema-version column is absent.

## History API and verification

`GET /api/audit/{context}/{uuid}?limit=25&before={auditId}` returns descending audit IDs and `nextBefore`. History remains readable after a record is deleted. Profile child rows use the owning person ID, family associations/addresses use the family ID, and identity/life-event associations use their parent ID, so those changes appear in the owning record history. Each context requires its management permission; identity/media require `identity.manage`, and reporting requires `reports.manage`. The existing workflow history screen shows field changes. On MySQL, a 501 response causes it to use the legacy metadata journal; permission failures never trigger that fallback.

CI runs PostgreSQL migrations plus the full PHP suite with `POSTGRES_TEST_URL` and `MYSQL_TEST_URL`. Dedicated integration tests cover actual PostgreSQL triggers, secret redaction, rollback, failed audit insertion, concurrent writers, shared advisory locks, runtime-role restrictions, cursor pagination, stable association rows and a MySQL-to-PostgreSQL transfer with binary/JSON fixtures and failure rollback. SQLite remains part of service/HTTP coverage. Production-size load testing and real data reconciliation remain rollout checks rather than claims made by this fixture suite.

### Migration order when importing MySQL data

For a MySQL transfer, migrate a fresh PostgreSQL target only through
`DoctrineMigrations\Version20261021020000` before running the import. The transfer
requires an empty audited schema with only baseline permissions. After the
verified transfer, run migrations to latest to seed administrator permissions.
A database already migrated to latest contains the default role and audit history
and is therefore not an empty import target; use a fresh database.
