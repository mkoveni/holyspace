# Java API on Ubuntu with host Nginx

Use this target when Nginx already runs on the VPS. Host Nginx owns ports 80/443
and HTTPS; Docker runs the Java API, PostgreSQL and maintenance tools. The API
publishes only `127.0.0.1:18082`, and PostgreSQL publishes no port. The bundled
Vue and Caddy containers are not deployed. This is a single-host deployment.

The frontend can be hosted separately. Set `PAYMENTS_PUBLIC_URL` to its HTTPS
origin, `PAYMENTS_BACKEND_URL` to the API HTTPS origin and `CORS_ALLOWED_ORIGINS`
to the exact trusted frontend origins. Mobile calls the public API origin.
The current Vue Java build uses same-origin `/api`; its frontend host must proxy
that path to the public API over HTTPS. A separately hosted frontend is not
configured by this backend deployment.

## Prepare the host

Use a maintained Ubuntu release with security updates, Nginx, Docker Engine and
Docker Compose v2 supporting `!reset`, plus Bash, Python 3, OpenSSL, curl and
sha256sum. Follow the [production runbook](java-production-runbook.md) for capacity,
secret generation, backups and administrator setup. Only SSH and 80/443 should
be publicly reachable. Do not rely on a host firewall to protect Docker-published
ports; retain the explicit loopback binding. Other local host processes can reach
the API, so this assumes a trusted VPS without untrusted local users/workloads.

Point the API DNS A record at the VPS; publish AAAA only if IPv6 is configured.
Use an API domain such as `api.your-church.org`, distinct from the frontend domain.
The proxy configuration assumes Nginx is directly exposed to clients. If a CDN or
load balancer is added, review trusted proxy addresses and client-IP handling.

Copy `.env.java-production.example` to ignored `.env.java-production.local` and
configure the domains, immutable release tag and private secret paths. Keep
`CHURCH_DOMAIN` consistent with the frontend origin even though it is hosted
elsewhere. Preserve encryption keys across releases.

## Select this deployment target

Run from the repository root. Keep this setting for every production operation,
including bootstrap, backup, restore, checks and security scans:

```sh
export JAVA_PRODUCTION_TARGET=nginx-api
python3 scripts/java-production/preflight.py
scripts/java-production/deploy.sh
```

The scripts automatically merge `compose.java-production-nginx.yaml`. They build
and scan only the API, database and tools, then migrate and start the API with
restricted runtime credentials. High/critical image findings still stop deployment;
the database findings remain unresolved. Host Nginx and Ubuntu require their own
security updates and review; excluding unused Caddy does not certify the host.

This target is intended for a fresh installation. Do not switch targets on a live
bundled deployment without a maintenance plan: already-running containers are not
removed automatically. Never remove a database volume to change deployment targets.

For subsequent releases:

```sh
export JAVA_PRODUCTION_TARGET=nginx-api
scripts/java-production/deploy.sh age1YOUR_PUBLIC_RECIPIENT /secure/holyspace-backups
scripts/java-production/check.sh
```

Schedule jobs with the same target, environment-file path and working directory.
The generic `verify.sh` explicitly rehearses the complete bundled topology; it
does not validate this VPS's Nginx, certificates or firewall.

After building the release-check web image with that rehearsal, run
`python3 scripts/java-production/verify-nginx.py` for isolated host-Nginx target
and live proxy checks. This uses a temporary certificate and an echo upstream;
it checks routing, body limits, private logs and spoofed forwarding headers.
CI runs both checks. Real VPS acceptance remains required below.

## Configure Nginx and certificates

Install Certbot using the supported method for the host. Create an ACME webroot
at `/var/www/letsencrypt`. First enable an HTTP-only API site containing the
port-80 server from
[`nginx-api.conf`](../docker/java-production/nginx-api.conf), replacing the
example domain in every occurrence. Do not enable the TLS server until its
certificate files exist. This leaves ACME challenges available while other
requests redirect to HTTPS.

After checking and reloading Nginx, obtain the certificate:

```sh
sudo nginx -t
sudo systemctl reload nginx
sudo certbot certonly --webroot -w /var/www/letsencrypt -d api.your-church.org
```

Then install the complete supplied configuration as
`/etc/nginx/sites-available/holyspace-api`, replace all domain/certificate paths,
and enable it in `sites-enabled` if not already enabled. Preserve unrelated sites.
Check the configuration before reloading:

```sh
sudo nginx -t
sudo systemctl reload nginx
sudo certbot renew --dry-run
```

Configure Certbot's renewal mechanism with a successful-renewal deploy hook that
runs `nginx -t` and reloads Nginx; verify the timer and hook on this host. Keep the
ACME HTTP location available. Confirm access/error log rotation is active.

Nginx overwrites forwarded identity/protocol headers, exposes only `/api/` and
generic `/health/ready`, and limits bodies to 6 MiB. It leaves application payment
CSP headers intact. Its private access format omits URLs and query strings that
could contain payment capabilities. Do not add permissive CORS headers at Nginx;
the API evaluates configured origins.

## Acceptance on the VPS

- Check `https://api.your-church.org/health/ready` returns healthy after deployment.
- Verify `/docs`, `/v3/api-docs` and `/actuator/env` are inaccessible publicly.
- Verify the API listener is bound only to `127.0.0.1:18082`; from a separate
  machine, ports 18082, 8082 and 5432 must be unreachable on both IP families.
- Test frontend login and mobile login against the real HTTPS API; verify CORS
  rejects an unrelated origin and spoofed forwarding headers do not alter URLs.
- Confirm payment settings use the frontend for browser returns and the API for
  provider callbacks. Complete controlled provider acceptance before enabling them.
- Complete administrator setup, off-site backups, restored-application recovery,
  alerts and the remaining [launch gates](production-readiness.md).

No VPS access, firewall changes, certificate issuance or public deployment is
performed by adding this configuration to the repository.
