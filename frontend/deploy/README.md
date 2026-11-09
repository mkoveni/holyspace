# Production deployment of the web app (holyspace.rivalani.dev)

The frontend is a static Vite build served by the host nginx on the same VPS as the Symfony API. The browser loads
the app from `holyspace.rivalani.dev` and calls the API at its own host, `holyspace-api.rivalani.dev` (cross-origin,
bearer tokens, no cookies).

```
browser ──HTTPS──▶ nginx holyspace.rivalani.dev ─────▶ /srv/onechurch-web/current (static files)
   └────HTTPS /api/*──▶ nginx holyspace-api.rivalani.dev ──unix socket──▶ php-fpm "onechurch-api"
```

The build targets the Symfony port of the Java API contract
with `VITE_API_BASE_URL=https://holyspace-api.rivalani.dev` (set `API_URL=https://...` for `build.sh` to change it;
then pass the same host to `setup-server.sh --api-domain`). Source maps are removed from the release.

| File | Purpose / installed as |
| --- | --- |
| `build.sh` | builds `dist/` and packs it (used by both `release.sh` and CI) |
| `setup-server.sh` | run once on the server (re-runnable) |
| `release.sh` | run on your machine to build and ship a commit |
| `remote-install.sh` | also `/usr/local/sbin/onechurch-web-install` |
| `rollback.sh` | `/usr/local/sbin/onechurch-web-rollback` |
| `ci-receive.sh` | `/usr/local/sbin/onechurch-web-receive` (forced command of the CI key) |
| `bootstrap-ci.sh` | run once on your machine: server setup plus GitHub CI/CD wiring |
| `nginx/onechurch-web.conf` | `/etc/nginx/sites-available/onechurch-web` |

Server layout:

```
/srv/onechurch-web/
  current -> releases/20261108093000-a1b61ed0c4f2   (atomic symlink switch; no nginx reload needed)
  releases/<utc-timestamp>-<sha>/                   (last 5 kept; root-owned, read-only to nginx)
  shared/deploy.conf                                (web and API domains)
```

Each release also serves the hashed `/assets/*` files of the two releases before it, so a tab opened before a
deploy can still lazy-load the pages it was built with. `index.html` is sent with `Cache-Control: no-cache`, assets
with a one-year immutable cache.

## Before you start

- The API runs at `https://holyspace-api.rivalani.dev` (`backend-symfony/deploy`).
- DNS: an `A` record for `holyspace.rivalani.dev` pointing at the VPS.
- Locally for `release.sh`: Node `^22.22.2 || >=24.15.0`, git, ssh.
- In the API's `/srv/onechurch-api/shared/.env.local`, then redeploy the API (values are compiled per release):
  - `CORS_ALLOW_ORIGIN='^https://holyspace\.rivalani\.dev$'`. **Required**: without it the browser blocks every API
    call, including sign-in. Each web deploy checks this and prints a warning if the API does not allow the origin.
  - `PAYMENTS_PUBLIC_URL=https://holyspace.rivalani.dev` (payment return pages are on the web app).

## 1. Prepare the server (once)

```bash
scp -r frontend/deploy you@your-vps:/tmp/onechurch-web-deploy
```

```bash
ssh -t you@your-vps 'sudo bash /tmp/onechurch-web-deploy/setup-server.sh --certbot-email you@example.org'
```

`--domain` defaults to `holyspace.rivalani.dev` and `--api-domain` to `holyspace-api.rivalani.dev`. It creates `/srv/onechurch-web`, installs the deploy commands and
the nginx site (HTTP-only until a certificate exists), obtains the certificate with certbot's webroot method and then
enables the HTTPS site. It never touches the API site, other sites or existing releases.

## 2. Deploy a release by hand

From the repository on your machine (commit first: only committed files are built):

```bash
frontend/deploy/release.sh you@your-vps
```

or a tag or commit: `frontend/deploy/release.sh you@your-vps v1.0.0`. It runs `npm test`, type-checks and builds,
uploads the archive and installs it: unpack, carry earlier assets, switch `current`, then check over HTTPS that `/`,
`/login` and the entry bundle are served by the new release. If that fails it switches back to the previous release.
It also reports whether the API's `/health/ready` is UP and whether its CORS allows the web origin, without failing
the web deploy for either.

## CI/CD: automatic deploys from `main`

`.github/workflows/frontend-release.yml` runs on every push and pull request touching `frontend/`:

1. **checks**: `npm ci`, `npm audit --audit-level=high`, `npm test`, shellcheck of `deploy/*.sh`, `nginx -t` of the site.
2. **package**: `deploy/build.sh` (type-check and production build) and uploads the archive.
3. **deploy** (push to `main` only, only when `FRONTEND_DEPLOY_ENABLED=true`): skips a superseded commit, checks the
   server's deploy scripts match the commit, streams the archive over SSH to the installer and checks the public site.

Wire it once (needs `gh auth login` with admin rights on the repository):

```bash
frontend/deploy/bootstrap-ci.sh you@your-vps --certbot-email you@example.org
```

It generates a deploy key and runs the server setup with it. The key belongs to `onechurch-web-deploy`, a user of its
own (the API's `onechurch-deploy` key stays pinned to the API installer), and is restricted to
`command="/usr/local/sbin/onechurch-web-receive"`: it can only `deploy <release-id>` or print `version`. sudo allows
exactly `/usr/local/sbin/onechurch-web-install --stdin *`. Secrets go into the GitHub environment
`frontend-production` (main only): `FRONTEND_VPS_SSH_KEY`, `FRONTEND_VPS_HOST`, `FRONTEND_VPS_KNOWN_HOSTS`,
environment variable `FRONTEND_VPS_PORT`, repository variables `FRONTEND_URL` and `FRONTEND_DEPLOY_ENABLED=true`.

A commit that changes `remote-install.sh` or `ci-receive.sh` makes the deploy job stop until you re-run
`bootstrap-ci.sh` (or `setup-server.sh`) once, because the server's copies are root-owned. Set
`FRONTEND_DEPLOY_ENABLED=false` to pause deploys.

## Operations

| Task | Command (on the server) |
| --- | --- |
| Current release | `readlink /srv/onechurch-web/current` |
| Roll back | `sudo onechurch-web-rollback [release-id]` |
| nginx logs | `/var/log/nginx/onechurch-web.{access,error}.log` (no URLs: public form links carry tokens) |
| Certificate | `sudo certbot renew --dry-run` |

## Acceptance checklist

- [ ] `https://holyspace.rivalani.dev/` loads the sign-in page; `http://` redirects to `https://`.
- [ ] A deep link such as `https://holyspace.rivalani.dev/people` reloads without a 404.
- [ ] Sign-in works, and the browser's network tab shows `/api/...` requests going to `holyspace-api.rivalani.dev`
      with no CORS errors in the console.
- [ ] A public form link (`/forms/public/<token>`) opens signed out, with its banner image.
- [ ] A test-mode payment returns to the web app (`PAYMENTS_PUBLIC_URL`).
- [ ] After a second deploy, a tab left open from before can still open a page it had not visited.
- [ ] `sudo onechurch-web-rollback` switches back and the site keeps working.
