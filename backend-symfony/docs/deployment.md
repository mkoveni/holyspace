# Production deployment (Ubuntu VPS, host nginx, PHP-FPM)

The Symfony API runs natively on the VPS: nginx terminates HTTPS and passes `/api/*` straight to a dedicated PHP-FPM
pool over a unix socket; one systemd worker runs the scheduler (email and push dispatch every 15 seconds). No Docker.

```
client ──HTTPS──▶ nginx :443 ──unix socket──▶ php-fpm pool "onechurch-api" (user onechurch)
                                                     │
systemd onechurch-api-worker (messenger:consume) ────┴──▶ PostgreSQL (local or managed)
```

Everything lives in `deploy/`:

| File | Installed as |
| --- | --- |
| `bootstrap-ci.sh` | run once on your machine: server setup plus GitHub CI/CD wiring |
| `setup-server.sh` | run once on the server (re-runnable) |
| `release.sh` | run on your machine for every release |
| `remote-install.sh` | run by `release.sh` on the server |
| `rollback.sh` | `/usr/local/sbin/onechurch-api-rollback` |
| `remote-install.sh` | also `/usr/local/sbin/onechurch-api-install` (what CI triggers) |
| `ci-receive.sh` | `/usr/local/sbin/onechurch-api-receive` (forced command of the CI key) |
| `env.prod.example` | `/srv/onechurch-api/shared/.env.local` |
| `nginx/onechurch-api.conf` | `/etc/nginx/sites-available/onechurch-api` |
| `nginx/onechurch-api-fastcgi.conf` | `/etc/nginx/snippets/onechurch-api-fastcgi.conf` |
| `php/fpm-pool.conf` | `/etc/php/<ver>/fpm/pool.d/onechurch-api.conf` |
| `php/99-onechurch.ini` | `/etc/php/<ver>/{fpm,cli}/conf.d/99-onechurch.ini` |
| `systemd/onechurch-api-worker.service` | `/etc/systemd/system/onechurch-api-worker.service` |

Server layout:

```
/srv/onechurch-api/
  current -> releases/20261108093000-a1b61ed0c4f2   (atomic symlink switch)
  releases/<utc-timestamp>-<sha>/                   (last 5 kept)
  shared/.env.local                                 (secrets, mode 0640)
  shared/var/share                                  (cache pools that survive releases: scheduler state, rate limits)
  shared/var/lock                                   (flock store shared by FPM and the worker)
  shared/deploy.conf                                (PHP version and domain)
/var/log/onechurch-api/                             (PHP errors, FPM slow log; logrotate weekly)
```

## Before you start

- DNS: an `A` record for the API host (for example `api.your-church.org`) pointing at the VPS. Add `AAAA` only if IPv6
  works on the host.
- Ubuntu with security updates, nginx already installed, a sudo-capable SSH user.
- PHP **8.4.1 or newer** with `php8.4-fpm` and `php8.4-cli`, installed by you on the host (8.3 cannot run this
  project: Symfony 8.1 and the code need 8.4). Required extensions: `pdo_pgsql`, `mbstring`, `ctype`, `iconv`,
  `openssl`; recommended: OPcache, `intl`, `curl`. Composer must be on the `PATH`. The setup script installs none of
  these; it checks them and lists anything missing.
- PostgreSQL 13 or newer, already running on the host (yours is 16; nothing installs or upgrades it).
  `--create-database` creates a non-superuser `onechurch` role that owns an `onechurch` database, and writes
  `DATABASE_URL` with `serverVersion` matching the server. Existing roles and databases are left untouched. CI runs
  the test suite on PostgreSQL 16 so it matches production. Migrations V1–V35 were checked on 16.15 as that role.
- **Do not put a CDN or another proxy in front.** Payfast notifications are authorised by source IP, which the app reads
  from nginx's real client address. A proxy would need `trusted_proxies` and a review first.

### If the Java backend already runs on this VPS or database

- The SQL migrations are shared (V1–V30 are Java's Flyway files; V31+ are additive), so the Symfony app can use the
  same database. Take a backup first.
- `PAYMENTS_TOKEN_KEY` and `PUSH_TOKEN_KEY` **must be the Java deployment's keys**, or stored provider credentials,
  subscription tokens and device tokens cannot be decrypted.
- **Run only one scheduler.** Both backends dispatch the same email and push queues. Stop the Java API (or its
  schedulers) before starting `onechurch-api-worker`, or members get duplicate messages.
- Use a different API host name, or replace the Java nginx site; do not enable both for one `server_name`.

## 1. Prepare the server (once)

From your machine, copy the deploy folder up and run the setup:

```bash
scp -r backend-symfony/deploy you@your-vps:/tmp/onechurch-deploy
```

```bash
ssh -t you@your-vps 'sudo bash /tmp/onechurch-deploy/setup-server.sh --domain api.your-church.org --create-database --certbot-email you@your-church.org --ufw'
```

Options: `--php 8.4` (default; pass the installed version if newer); drop `--create-database` if the role and database already exist (then set `DATABASE_URL` yourself); drop
`--certbot-email` to issue the certificate yourself (the site then stays HTTP-only until you re-run with it); drop
`--ufw` if you manage the firewall elsewhere (only SSH, 80 and 443 should be open).

It checks PHP, its extensions and Composer, creates the `onechurch` system user and `/srv/onechurch-api`, installs the FPM pool, PHP settings, worker unit, logrotate and nginx site (HTTP-only until a
certificate exists, then the full HTTPS site and a certbot reload hook). It generates `APP_SECRET` and, with
`--create-database`, a database role and password written into `DATABASE_URL`. It never removes data or other sites.

## 2. Fill in the secrets

```bash
ssh -t you@your-vps 'sudo -u onechurch nano /srv/onechurch-api/shared/.env.local'
```

Required before the first deploy (the install refuses to run while template values remain):

| Key | Value |
| --- | --- |
| `DATABASE_URL` | already set with `--create-database`; otherwise your database URL with `serverVersion=16` |
| `DEFAULT_URI` | `https://api.your-church.org` |
| `CORS_ALLOW_ORIGIN` | regex of the exact frontend origins, e.g. `'^https://your-church\.org$'` |
| `PAYMENTS_PUBLIC_URL` / `PAYMENTS_BACKEND_URL` | frontend origin / API origin |
| `PAYMENTS_TOKEN_KEY` | `openssl rand -base64 32` (or the Java key) |
| `PUSH_TOKEN_KEY` | `openssl rand -base64 32` (or the Java key) |
| `MAILER_DSN`, `COMMUNICATIONS_EMAIL_FROM` | your SMTP relay; keep `COMMUNICATIONS_EMAIL_ENABLED=false` until tested |

Keep payments in `test` mode and push and email disabled until each is accepted end to end. Back up
`.env.local` somewhere safe and offline: losing `PAYMENTS_TOKEN_KEY` loses the stored credentials for good.

## 3. Deploy a release

From the repository on your machine (commit first: only committed files are shipped):

```bash
backend-symfony/deploy/release.sh you@your-vps
```

or a specific tag or commit: `backend-symfony/deploy/release.sh you@your-vps v1.0.0`.

`remote-install.sh` then unpacks into a new release directory, runs `composer install --no-dev
--classmap-authoritative`, compiles the environment (`composer dump-env prod`), warms the cache, applies migrations
(`app:migrate`), switches `current` atomically, reloads PHP-FPM (which also resets OPcache), restarts the worker and
checks `https://<domain>/health/ready`. If that check or the worker fails it switches back to the previous release.

Migrations run before the switch while the old release still serves traffic. They are additive by project rule, so
that is safe, and a rollback does not undo them.

After changing `.env.local`, deploy again (the values are compiled into each release).

## CI/CD: automatic deploys from `main`

`.github/workflows/symfony-release.yml` runs on every push and pull request that touches `backend-symfony/`:

1. **checks**: `composer validate`, php-cs-fixer (dry run), PHPStan, Deptrac, PHPUnit against PostgreSQL 16,
   `composer audit`, shellcheck of `deploy/*.sh` and `nginx -t` of the site config.
2. **package**: builds the release from the committed tree with production dependencies (`--no-dev
   --classmap-authoritative`) on PHP 8.4, boots the prod kernel (`cache:warmup`, `lint:container`) and uploads the
   archive. The server therefore runs exactly the vendor/ that was tested; it does not need to reach Packagist.
3. **deploy** (only a push to `main`, only when `SYMFONY_DEPLOY_ENABLED=true`): skips itself if a newer `main` commit
   exists, checks the server's deploy scripts match the commit, streams the archive over SSH to the installer
   (migrate, switch, health check, automatic switch-back) and finally checks `https://<api>/health/ready` publicly.
   Production deploys never run in parallel and are never cancelled midway.

Pull requests get no deployment secrets: they live in the GitHub environment `symfony-production`, which only `main`
may use.

### One-time wiring (the only manual step)

You need SSH access to the VPS as a sudo user and the GitHub CLI logged in (`gh auth login`) with admin rights on the
repository. Then, from the repository root:

```bash
backend-symfony/deploy/bootstrap-ci.sh you@your-vps --domain api.your-church.org --certbot-email you@your-church.org
```

Any `setup-server.sh` option can be added (`--create-database`, `--ufw`, `--php 8.4`). It generates a fresh deploy key,
runs the server setup with it, reads the host key over your verified SSH connection, and stores everything in GitHub:
environment secrets `SYMFONY_VPS_SSH_KEY`, `SYMFONY_VPS_HOST`, `SYMFONY_VPS_KNOWN_HOSTS`, environment variable
`SYMFONY_VPS_PORT`, repository variables `SYMFONY_API_URL` and `SYMFONY_DEPLOY_ENABLED=true`.

Then fill in `/srv/onechurch-api/shared/.env.local` once (section 2). From then on, merging to `main` deploys.

### What the CI key can do

It belongs to `onechurch-deploy`, a locked user whose `authorized_keys` entry is
`restrict,command="/usr/local/sbin/onechurch-api-receive"`: no shell, no port or agent forwarding, and whatever the
client asks for, only `deploy <release-id>` (archive on stdin) and `version` are accepted. sudo allows that user
exactly `/usr/local/sbin/onechurch-api-install --stdin *`. The installer and receiver are root-owned copies, so a
leaked key can install a release but cannot change how releases are installed or gain a root shell.

Because those copies are root-owned, a commit that changes `remote-install.sh` or `ci-receive.sh` makes the deploy
job stop with "deploy scripts on the server differ". Re-run `bootstrap-ci.sh` (it also rotates the key) and re-run
the job. To pause deploys, set `SYMFONY_DEPLOY_ENABLED` to `false`.

## 4. Create the first administrator

```bash
ssh -t you@your-vps
```

```bash
sudo -u onechurch sh -c 'umask 077; cat > /srv/onechurch-api/shared/admin-password'   # type the password, then Ctrl-D
```

```bash
sudo -u onechurch php /srv/onechurch-api/current/bin/console app:identity:bootstrap-admin --username admin --email you@your-church.org --display-name "Administrator" --password-file /srv/onechurch-api/shared/admin-password
```

```bash
sudo shred -u /srv/onechurch-api/shared/admin-password
```

## Operations

| Task | Command (on the server) |
| --- | --- |
| Health | `curl -fsS https://api.your-church.org/health/ready` |
| Worker status / logs | `systemctl status onechurch-api-worker`, `journalctl -u onechurch-api-worker -f` |
| Application logs (JSON) | `/var/log/onechurch-api/php-error.log` (FPM), journal (worker) |
| nginx logs | `/var/log/nginx/onechurch-api.{access,error}.log` (access log has no URLs: they can hold payment tokens) |
| Roll back | `sudo onechurch-api-rollback [release-id]` |
| Console | `sudo -u onechurch php /srv/onechurch-api/current/bin/console <command>` |
| Database backup | `sudo -u postgres pg_dump -Fc onechurch > /secure/onechurch-$(date +%F).dump` (schedule it and copy off-site) |

## Acceptance checklist

- [ ] `https://api.your-church.org/health/ready` returns `{"status":"UP"}`.
- [ ] `https://api.your-church.org/` and `/actuator/health` return 404; `http://` redirects to `https://`.
- [ ] From another machine, only ports 22, 80 and 443 answer (PostgreSQL 5432 is not reachable).
- [ ] Frontend and mobile login work against the HTTPS API; a preflight from an unrelated origin gets no
      `Access-Control-Allow-Origin`.
- [ ] `systemctl is-active onechurch-api-worker` is `active`, and exactly one scheduler runs (Java stopped).
- [ ] Payments: provider dashboards point callbacks at `https://api.your-church.org/api/payments/webhooks/{payfast,yoco}`;
      one test-mode payment completes before switching to live.
- [ ] Email and push each tested before enabling.
- [ ] A backup was restored once into a scratch database.
- [ ] `sudo certbot renew --dry-run` succeeds.

## What was verified locally

The prod build (`composer install --no-dev`, `dump-env prod`, `cache:warmup`, `lint:container`), the nginx and FPM
configuration (in containers with a self-signed certificate: routing, 404s, ignored spoofed forwarding headers, HSTS,
CORS, private access log) and the worker loop. A full CI-style rehearsal in containers: the release built as the package job builds it, streamed through the
restricted receiver into the installer against PostgreSQL 18 (V1-V35 applied), redeployed, rolled back, with
refused shell, injection and other-sudo attempts. On PostgreSQL 16.15: migrations as the non-superuser owner and the full suite (1534 tests) pass. The workflow passes actionlint. Not yet run on a real VPS or in
GitHub Actions (the PHPUnit suite in CI has not had its first run).

## Known follow-ups

- Symfony 8.1 reaches end of maintenance in January 2027. Plan the upgrade to the next minor before then.
- `.github/workflows/java-release.yml` can also deploy the Java backend to this VPS on `main`. If both are enabled,
  both schedulers send email and push: disable `PRODUCTION_DEPLOY_ENABLED` (and stop Java) when Symfony takes over.
