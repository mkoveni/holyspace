# Staff pilot: production deployment and operations

This deployment targets one church on one Linux Docker host. It provides HTTPS, built application images, a private MySQL service, background workers, shared file locks, readiness checks, bounded logs, and encrypted backup tooling. It does not provision a server, domain, email/SMS accounts, off-site backup storage, or an external alerting service.

## Access policy

The backend enforces these permissions for all matching routes, including nested records and autocomplete:

| Area | View | Create/change/delete |
| --- | --- | --- |
| People, profiles, relationships | `people.read` | `people.manage` |
| Families and household overviews | `families.read` | `families.manage` |
| Events and occurrences | `events.read` | `events.manage` |
| Life Events | `life_events.read` | `life_events.manage` |

Management includes read access. Other operational contexts continue to require their existing `*.manage` permissions. Account management requires `identity.manage`. Migration `Version20261011020000` grants the new permissions to existing named administrator roles; it does not grant them to every account. For a fresh database, use the operator-only `app:accounts:bootstrap` command described below. It refuses databases with existing accounts; no default production account or password is shipped.

Staff using operational forms also need `people.read` and `events.read` to load reference data. Add `families.read` and `life_events.read` when those screens are part of their job. A read permission currently covers the area's complete records, not field-level, campus-level, or own-record access. Do not invite ordinary members with staff permissions.

After permission changes, staff should sign out and back in. The API always checks current permissions, even when a browser has an old menu. Back up before migrating. Assign permissions to any nonstandard administrator roles explicitly before rollout.

## Host and secrets preparation

Use Docker Engine with Compose support for `include` and `--wait`, Bash, OpenSSL, and curl. Allocate persistent disk space, restrict SSH access, and point the church's domain at the host. Allow inbound 80/443 for Caddy certificate issuance and HTTPS; keep database, PHP, and internal web ports private.

Copy `.env.production.example` to `.env.production.local`. Fill the required values with strong independent secrets. `DATABASE_PASSWORD_URLENCODED` must contain the same password as `DATABASE_PASSWORD`, percent-encoded for a URL. Real environment files, private keys, and encrypted backups are ignored by Git and excluded from build context.

Create a password-protected RSA private key and matching public key on the host. Use the passphrase from `JWT_PASSPHRASE`. Keep keys outside the checkout; set the two file paths in the environment file. The PHP container runs as UID/GID 33: source private keys must be readable by that group, for example owner root, group 33, mode 0640. Compose file-backed secrets preserve source file access; do not assume Compose will change file ownership for you. Keep the private key out of logs and CI artifacts.

Provider defaults cannot send. Configure a verified sender and tested mail/SMS adapter only when outbound delivery is approved. Starting the communications worker with configured credentials can send any queued campaigns.

## Initial deployment

Run from the repository root:

```bash
docker compose --env-file .env.production.local -f compose.production.yaml build php web tools
docker compose --env-file .env.production.local -f compose.production.yaml up -d --wait database
docker compose --env-file .env.production.local -f compose.production.yaml run --rm -T --no-deps php php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.production.local -f compose.production.yaml up -d --wait --wait-timeout 180 php web outbox communications followups edge
scripts/production/check.sh
```

`deploy.yaml` is a compatibility alias for this production configuration. Development still uses `docker-compose.yaml`.

For the first administrator, prepare a temporary private file with a strong password (at least 12 characters), readable by container UID/GID 33. Do not put the password on the command line:

```bash
docker compose --env-file .env.production.local -f compose.production.yaml run --rm -T --no-deps -v /secure/operator/bootstrap-password:/run/secrets/bootstrap-password:ro php php bin/console app:accounts:bootstrap --username administrator --password-file /run/secrets/bootstrap-password
```

Remove the temporary password file after successful setup and store credentials in your password manager. The command takes a lock, creates the first administrator transactionally, grants existing permissions, and refuses later runs once any account exists. Existing installations must use an authorised administrator to assign permissions; the bootstrap command is not an account-recovery bypass.

The application is served at the configured HTTPS domain. Caddy manages certificates. The frontend uses same-origin `/api` requests. PHP and workers share a dedicated lock volume; this is suitable for containers on the same host. Multi-host deployments require a shared distributed lock store, shared rate-limiter storage, and a separate scaling design. Never run the production workers with separate default `/tmp` lock directories.

`/health` is liveness. `/health/ready` checks database connectivity and the baseline schema/permissions, returns 503 on failure, and exposes no database error details. It is not a complete schema checksum or a backup check. Worker containers update local heartbeats after successful passes. A failed pass exits and Docker restarts the worker. Docker marks a stalled worker unhealthy, but does not automatically restart a still-running unhealthy process: monitoring must alert an operator.

Login throttling limits repeated failed attempts using Symfony's rate limiter. Trusted forwarded headers are configured only for production, whose internal services are private behind Caddy. Do not expose the internal web service directly to untrusted callers. Multiple PHP instances need shared limiter storage. Recreating a single PHP container currently resets its file-backed limiter state.

## Release and rollback

Use a unique `RELEASE_TAG` and retain the previous backend/web images. Run CI before release. Take an encrypted backup, inspect migrations, and use a maintenance window for schema-changing releases. Drain or stop workers during incompatible migrations. Deploy images, run migrations, start workers, and verify readiness and staff smoke tests before reopening.

Do not automatically run migration `down` to roll back financial/custody data. Application rollback is safe only when the previous application supports the migrated schema. For an incompatible change, use the tested recovery plan and an explicitly chosen recovery point. This repository does not automatically deploy or roll back the live server.

## Monitoring and incident handling

Schedule `scripts/production/check.sh` every minute. It checks container state/health and runs `app:operations:check`, which fails on missing baseline schema, 500+ pending outbox entries, an oldest pending entry at least five minutes old, or a sending attempt older than five minutes. Adjust thresholds after observing the pilot. Unknown legacy sending timestamps also require attention.

Connect nonzero exit codes and public readiness failures to an external alerting system with an assigned responder. `docker/production/cron.example` provides host schedules; it is a template and is not installed automatically. Log files alone are not alerts. Route container logs to your chosen central collector; this deployment bounds local Docker log growth but does not provision that collector.

Responses carry `X-Request-ID`. Unexpected failures emit a matching request ID, route name, and exception class in application logs. Do not copy donor, child, screening, or credential data into incident reports. Investigate an outbox failure by its envelope reference, repair the cause, then restart processing. Do not skip an unknown envelope to make a dashboard appear healthy.

For uncertain message delivery, check the provider before resolving an attempt. Never automatically resend an uncertain message. See the bounded-context roadmap for reconciliation rules.

## Encrypted backups

Build the `tools` image during deployment. Generate an age keypair on a secure operator machine; keep the private identity separately from the deployment host and keep a recoverable offline copy. Only the public recipient is needed for backups.

```bash
scripts/production/backup.sh age1YOUR_PUBLIC_RECIPIENT /secure/church-backups
```

The script streams a transaction-consistent MySQL dump directly through age encryption, writes a private temporary ciphertext file, and publishes the backup only if both commands succeed. It creates no plaintext SQL file. Do not run migrations or other DDL while a dump is in progress. Record backup success and upload ciphertext to independent off-site storage. Choose retention and alert on missed backups; local disk alone is insufficient.

Install the example daily backup schedule with real paths and the public recipient. Record the recovery-point target (for example, 24 hours for daily backups) and recovery-time target agreed with church leadership. Higher recovery requirements need more frequent backups or database point-in-time recovery.

## Restore drill

```bash
scripts/production/restore-drill.sh /secure/church-backups/church-BACKUP.sql.age /secure/operator/age-identity
```

The drill decrypts into a temporary MySQL container with no network, published ports, or persistent volume. It does not write to the live database. It checks core tables and prints aggregate counts, then removes its container. Keep its results and compare with expected data. This structural drill is necessary but does not establish a full application recovery: before rollout, start a restored application against the recovered database and verify representative people, events, giving, custody history, and audit records. Test the recovered JWT keys and provider configuration separately.

## Automated release verification

GitHub Actions runs frontend tests/build/audit, backend tests/audit in a PHP image, and `scripts/production/verify.sh`. That script creates a separate production-shaped Compose project and a fresh MySQL database, applies all migrations, seeds explicitly isolated browser accounts, tests staff and read-only browser journeys, checks workers, and executes encrypted backup/restore verification. It removes only its own temporary project and volumes.

Local verification requires installed frontend dependencies and Docker. It uses port 5181 by default (override with `VERIFY_PORT`), official Playwright browser images, and isolated test credentials. CI can use installed browsers with `BROWSER_RUNNER=host`. Browser fixtures reject databases other than `church_ci` or `church_browser`; they are not shipped in the production PHP image. Traces and screenshots may contain test data; CI retains failure artifacts for seven days.

## Pilot acceptance and remaining work

Use `docs/staff-pilot-checklist.md` with real staff before expanding access. Production infrastructure verification is not real-user acceptance. MFA, self-service password recovery, field/campus/owner permissions, larger-data pagination, load testing, comprehensive browser coverage, off-site retention, and live-provider validation remain separate work. Do not describe this milestone as full production certification or One Church Software feature parity.

## Member portal security upgrade

Apply migrations Version20261012030000 and Version20261012040000 before starting the updated app. All previous JWTs are invalid after this upgrade. Keep APP_SECRET securely recoverable: it encrypts authenticator secrets, and must be preserved for a database restore. Do not rotate it without migrating those encrypted secrets. Member access requires an explicit member.portal grant and MFA. See [the member guide](member-portal.md) for the expiring, file-based offline administrator recovery command and the live pilot checks.

## Reporting and follow-up worker

Apply migration Version20261012050000 before starting updated workers. Start the followups service alongside outbox and communications. The operations check now reports followupRulesNeedingReview and fails when a rule needs review, even if the worker itself is healthy. Resolve the review in Reports and segments or keep the rule deliberately disabled after review. Daily rules create tasks only; no outbound notifications are sent. See [the reporting guide](reporting-segments-and-followups.md).

## Forms retention purge

`app:forms:purge` runs daily from the Symfony scheduler (`backend-symfony/src/Schedule.php`) and hard-deletes form
responses past their retention (at most 3 months), with the participation marks and leaderboards derived from them. The
audit table keeps only counts. Backups taken before a purge still contain the deleted responses until they expire:
either keep backup retention at or below the promised retention period, or state the longer backup retention in the
privacy notice.
