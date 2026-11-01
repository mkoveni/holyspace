# Java production deployment and recovery

This runbook is for the Java API, PostgreSQL 18 and the Vue **Java-mode** staff
workspace. It is separate from `production-runbook.md` and the PHP deployment.
There is no PHP data import. Target: one church on one Linux Docker host; this is
not a highly available or multi-tenant deployment.

For an Ubuntu VPS already running Nginx, use the
[host Nginx backend deployment](ubuntu-nginx-java-deployment.md). It publishes the
API only on loopback and omits the bundled frontend/Caddy containers. The rest of
this runbook describes the bundled topology unless stated otherwise.
See [backend CI/CD](backend-cicd.md) for automatic deployment of tested images.

## Prerequisites and launch gates

Use Docker Engine, current Docker Compose v2 (including `!reset` support), Bash,
Python 3, OpenSSL, curl and sha256sum. Reserve at least the API's 2 GiB memory
limit plus database, frontend and host capacity; determine final sizing with a
representative load test. Restrict SSH, keep the Docker socket private, and expose
only ports 80/443. Point two distinct DNS names at the host:

- Frontend, e.g. `church.your-domain.org`.
- Backend, e.g. `api.church.your-domain.org`.

Caddy manages HTTPS certificates for both and caps request bodies at 6 MiB on
both public origins. The staff browser uses same-origin
`/api`; phones use the public backend origin. Payment browser returns and provider
callbacks retain their separate frontend/backend origins. Public routes are
limited to the intended API capabilities; API documentation and other Actuator
endpoints are not exposed in production.

Review [the launch tracker](production-readiness.md) before opening access. The
patched backend images pass the current high/critical scan; bundled HTTPS proxy
findings remain a separate gate. No unfixed finding is automatically waived. The supported SDK 58 mobile security
patch is recorded in the launch tracker. Cloud
accounts, signing, DNS, external alerting and off-site storage are not provisioned
by this repository.

## Secrets and configuration

Copy `.env.java-production.example` to ignored `.env.java-production.local` and
replace the release tag, domains and paths. Use an immutable reviewed commit tag;
retain its images and the preceding release's images. Do not put secret values in
that environment file, Docker build arguments, Git or command-line arguments.

Create the private secret directory on the deployment host. The following is an
example for a fresh installation; **never regenerate keys over an existing
installation**:

```sh
sudo install -d -m 0700 -o "$USER" /srv/holyspace/secrets
umask 077
for name in admin_password runtime_password migration_password; do
  openssl rand -hex 32 > "/srv/holyspace/secrets/$name"
done
for name in payments_key push_key; do
  openssl rand -base64 32 > "/srv/holyspace/secrets/$name"
done
touch /srv/holyspace/secrets/smtp_password /srv/holyspace/secrets/push_access_token
sudo chgrp 10001 /srv/holyspace/secrets/*
chmod 0640 /srv/holyspace/secrets/*
```

The Java image uses UID/GID 10001. Compose mounts files with their source
permissions: its secret `uid`/`gid` settings do not fix bind-mounted source
ownership. Keep the directory private and the files readable by group 10001;
never make them world-readable. The database receives administrator and role
initialization secrets; API containers receive only the runtime password,
payments/push keys and optional SMTP/Expo credentials. Migration containers receive
only the migrator password. Spring imports API secrets through a configuration
tree, not interpolated environment values.

Store recoverable copies of encryption keys and database passwords in an
independent secret manager/password vault. Database backups alone cannot recover
encrypted payment credentials or push tokens. Encryption-key rotation requires a
separate migration/re-registration plan. Changing a password file does not change
an existing PostgreSQL role: rotate the role password and file together during a
controlled deployment. Database initialization runs only on an empty volume.

Run `python3 scripts/java-production/preflight.py`. It rejects missing/weak/reused
secrets, insecure/placeholder public origins and published internal ports. It does
not establish DNS reachability, successful TLS issuance or live provider readiness.

## First deployment

From the repository root:

```sh
scripts/java-production/deploy.sh
```

The script builds and security-scans the reviewed API/web/database/edge/tools
images before any downtime. High/critical findings stop the deployment. After
that gate passes it starts a private database,
stops application traffic, runs Flyway in a dedicated process, applies runtime
privileges and starts the production services. The migration command starts no
HTTP server, bootstrap runner or background delivery worker. The API has no
migration credentials, owns no schema objects and cannot change Flyway history.
Financial/custody triggers still enforce existing database invariants.

For the initial administrator, create a temporary private password file (minimum
12 characters) readable by group 10001. Use your password manager/editor; do not
put the password on the command line. Then run:

```sh
scripts/java-production/bootstrap.sh administrator admin@your-domain.org \
  'Church Administrator' /secure/operator/bootstrap-password
```

The script runs an isolated, unpublished API container with outbound services
disabled, waits for the committed bootstrap result, and removes that container.
The handler refuses any database that already has accounts. The ordinary API never
has bootstrap enabled. Remove the temporary password file after success. Set up
staff roles, permissions and verified member/donor links through administration;
do not give ordinary members staff permissions.

## Normal releases and rollback

Run all release checks first, review schema changes, retain the previous images
and schedule a maintenance window. For every subsequent deployment provide the
backup recipient and a private backup directory:

```sh
scripts/java-production/deploy.sh age1YOUR_PUBLIC_RECIPIENT /secure/holyspace-backups
```

The script detects an existing database **volume**, including stopped deployments,
and requires a successful encrypted backup before migration. A failed migration
leaves application traffic stopped. Inspect and repair the cause; never
force-start the API against a partially prepared schema or auto-run Flyway clean,
baseline or downgrade.

Roll back the application image only after confirming the previous application
supports the new schema. For incompatible migrations, use an agreed recovery point
and restored replacement database. Do not automatically reverse financial or
custody migrations. Rebuilding an old source tree is not a substitute for retaining
its verified image. Do not repoint a live app to an empty database volume.

## Backups, restore drills and recovery

```sh
scripts/java-production/backup.sh age1YOUR_PUBLIC_RECIPIENT /secure/holyspace-backups
scripts/java-production/restore-drill.sh /secure/holyspace-backups/BACKUP.dump.age \
  /secure/operator/age-identity
```

Backups stream a transaction-consistent PostgreSQL custom dump through age. No
plaintext dump is written to disk. The encrypted file is published only if both
pipeline commands succeed; a portable SHA-256 sidecar detects accidental damage.
Avoid concurrent migrations/DDL. Keep the private age identity outside the
application host and retain an offline recovery copy.

The drill decrypts into a disposable PostgreSQL container with no network,
published ports or persistent volume. It checks core Java tables and proves the
restored runtime role can read records but cannot create schema objects or delete
Flyway history. It removes only its own temporary container. It does not replace a
full restored-application exercise or test real credentials/provider callbacks.

For a real recovery: provision a **replacement** private database, initialize the
three roles, restore with `pg_restore --role=holyspace_migrator --no-owner --no-acl`,
run the matching release's migration command and `docker/java-production/grants.sql`,
restore the matching encryption keys, and smoke-test the application before
switching traffic. Disable outbound delivery during recovery; inspect queued and
UNKNOWN attempts before enabling workers. Never blindly resend uncertain payments
or messages. Validate account access, representative people, gifts, attendance,
collection evidence and audit history with authorized staff.

Install a backup schedule, transfer ciphertext and its checksum to independent
off-site storage, define retention and alert on missed backups. Agree recovery
point/time targets with the church. Daily backups imply up to a day's data loss;
use more frequent backups or tested PostgreSQL point-in-time recovery if that is
unacceptable. This code does not install a scheduler or off-site storage account.

## Health, queues and incident response

`/health/ready` is available through both public origins and returns generic
readiness. Internal liveness is `/actuator/health/liveness`; internal readiness is
`/actuator/health/readiness`. Readiness checks database availability; the release
procedure separately validates migrations. Liveness deliberately survives a DB
outage. Neither proves backups or provider delivery succeeded.

Run `scripts/java-production/check.sh` every minute through your host scheduler
and connect nonzero results to an external alert service with an assigned
responder. It checks container state/health, API readiness, overdue email/push jobs,
uncertain/failed deliveries and live charges/refunds waiting for ledger effects.
It prints aggregate counts only; normal DeviceNotRegistered push retirement is excluded. Review thresholds after pilot load; failed and
UNKNOWN rows need investigation, not automatic retry or deletion to silence an
alert. Historical unresolved rows can keep this check failing until reviewed.

Docker restarts crashed services and bounds local log size. An unhealthy but
running process is **not** automatically restarted by Docker: alert an operator.
Use external HTTPS uptime/TLS checks and host disk/memory monitoring. Monitor
certificate expiry, backup success and provider failures independently. Central
error reporting, dashboards and alert destinations still need an operational
integration. No personal payload or provider body belongs in alerts.

The frontend relay logs request IDs, methods, status and duration, omitting URLs
because checkout paths may contain one-use capabilities. Caddy access logs are not
enabled. Forwarded headers are trusted only because the API/web have no public
ports and sit behind the edge; do not publish them or reuse this trust model on an
untrusted shared network. Avoid enabling verbose Spring/jOOQ/request-body logging.

## Provider activation and mobile release

Email and push default off and capture mode is off. Configure a verified SMTP
sender/account with TLS, then explicitly approve and test delivery. Enabling a
provider may release existing approved queued messages; inspect the queue first.

Configure payment credentials through Administration using production HTTPS
origins. Validate provider webhooks, one-time mobile handoff, verified contribution
posting, reconciliation and refunds in an agreed live acceptance exercise. Do not
activate live giving merely because a connection probe passed.

Follow [device push setup](device-push-setup.md), configure Expo/Firebase and iOS
APNs credentials, build signed release applications and test them on physical
devices. No store credentials are stored in this repository. Eight-hour sessions
and explicit push re-enablement after expiry remain a member-experience/security
design decision before broad rollout.

## Isolated release rehearsal

```sh
scripts/java-production/verify.sh
```

This creates unique temporary secrets and a separate Compose project, performs
fresh and repeat migrations, creates a disposable administrator, tests login,
session revocation, CORS and the built Java-mode frontend in a real browser, runs aggregate operational
checks, backs up/restores encrypted data, and checks database-outage probes. It
uses loopback-only HTTP for the rehearsal, validates the real Caddy configuration
without requesting certificates, and cleans up only its own project and volumes.
It does not access the development database, use real provider secrets, send
messages or charge a donor. Real DNS/HTTPS, live providers, load, signing and staff
acceptance remain explicit release gates.

GitHub workflow `java-release.yml` runs Java verification, the isolated rehearsal,
Vue Java-mode tests/build/audit, and mobile checks/exports/browser workflows.
The existing PHP workflow is retained. A separate mobile audit job blocks release
on high/critical issues without hiding other test results. The backend CI image
gate uses a verified, digest-pinned Trivy scanner for the API, database and backup
tools, including OS and Java dependencies. The default bundled-target scan also
includes frontend and edge images. Run the full scan locally after the
rehearsal with `scripts/java-production/security-scan.sh release-check /tmp/holyspace-image-security`.
No unfixed finding is automatically ignored. The deployment command also runs
this gate before starting or stopping production services. The patched fresh
backend images pass the current scan; the bundled edge baseline still needs
upstream patches or a documented exploitability review. See the launch tracker.
