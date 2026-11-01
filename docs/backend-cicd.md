# Backend CI/CD to the Ubuntu VPS

The GitHub workflow `.github/workflows/java-release.yml` tests Java 25 with real
PostgreSQL integration tests, rehearses migrations and recovery, checks the host
Nginx boundary and scans the three backend runtime images. A successful trusted
`main` run packages those exact images and deployment files, then deploys over
SSH when explicitly enabled. Pull requests never receive deployment credentials.
Frontend/mobile jobs remain separate; their results are not dependencies of the
backend deployment. The existing rehearsal also exercises the bundled web relay.

This pipeline targets an **AMD64 Ubuntu VPS**, Docker Compose and host Nginx.
It transfers images directly over SSH: the VPS does not need GitHub repository or
container-registry credentials, a JDK, Maven or application source code.

## Single-script setup (recommended)

On an AMD64 Ubuntu 22.04/24.04/26.04/26.10 VPS, from a reviewed copy of this project:

```sh
sudo python3 scripts/java-production/setup-vps.py
```

The installer prompts for the API/frontend domains, certificate email, GitHub
repository and public SSH host/port. It installs dependencies, creates the deploy
account, generates missing secrets, installs the receiver, configures Nginx,
obtains/tests HTTPS certificate renewal and configures GitHub secrets/variables
through a browser device login. It enables automatic deployment for future
passing `main` releases. The source of these operations is the checked-in setup
script; review it before running with sudo.

If you prefer copying **one file** instead of a checkout, build the portable
installer on your workstation:

```sh
python3 scripts/java-production/setup-vps.py --bundle /tmp/holyspace-vps-setup.pyz
scp /tmp/holyspace-vps-setup.pyz administrator@your-vps:~/
```

Then run this single command on the VPS:

```sh
sudo python3 ~/holyspace-vps-setup.pyz
```

The portable file contains the same setup code and three public configuration
assets, with no credentials. The VPS needs Python 3.10+ already installed; Ubuntu
22.04's standard Python is supported without replacing the system interpreter. For a
preview, use `--plan` with `--api-domain`, `--frontend-domain`, `--email`,
`--repository` and `--ssh-host`; it makes no host or GitHub changes.
Trusted `main` workflow runs also publish a `holyspace-vps-setup` artifact so the
installer can be downloaded from Actions after these changes are merged.
CI checks installer/receiver behavior and official GitHub CLI installation in an
Ubuntu 22.04 container using its standard Python 3.10; backend deployment requires
that check to pass.
The installer obtains GitHub CLI from its official signed APT repository to
avoid the older distro client's missing features and retired API behavior.

Before running, point **every** API A/AAAA record directly at this VPS and allow
SSH/HTTP/HTTPS in the provider and host firewall. The installer leaves firewall
and SSH daemon settings alone. It refuses another site's use of the same domain
and preserves unrelated Nginx sites. Run provisioning in a maintenance window
because package installation can restart services. Certificate setup accepts the
Let's Encrypt subscriber agreement using the contact email you supply.

GitHub browser login requires repository administrator access and an account/plan
supporting production environments. Authentication is stored in a temporary
private CLI directory and removed at the end. Existing environment approval and
branch rules are preserved; new environments allow deployment only from `main`.
The setup does not change repository branch protection. If existing reviewers
are required, future deployments still wait for them.

Rerun the same command after an interrupted certificate/login step. Existing
database passwords, encryption keys, backup recipients and deployment keys are
preserved. A missing secret on a live installation stops setup rather than
regenerating it. A manually edited managed Nginx site also stops setup for review.
The root-only `/root/holyspace-setup` directory contains saved defaults, deployment
key and private GitHub setup data; do not copy it into releases or Git.

An age public recipient can be supplied at the prompt. If left blank, setup
generates `/root/holyspace-setup/backup-identity.txt`; copy that private identity
to independent recovery storage. Off-site backups, firewall review, privileged
MFA/recovery and production acceptance remain separate launch requirements.
No application is deployed by the installer, and image security gates remain
active. The workflow itself must be committed to `main` before it can run.

Use `--skip-github` to prepare the host without logging in; settings are saved
privately for the manual GitHub configuration below. Otherwise the manual steps
below are reference material rather than additional required setup commands.

The dependency and authentication steps follow the official
[Docker Ubuntu repository instructions](https://docs.docker.com/engine/install/ubuntu/),
[Certbot webroot workflow](https://eff-certbot.readthedocs.io/en/stable/using.html#webroot)
and [GitHub CLI login](https://cli.github.com/manual/gh_auth_login).

## Deployment behavior

1. Verify Java, architecture rules, migrations, restricted DB roles, recovery and
   proxy boundaries in CI. Reject high/critical backend-image security findings.
2. Tag the tested API/database/tools images with the full Git commit and package
   them with image IDs, file hashes and an archive checksum. Include only listed
   deployment files; production settings and secrets are excluded.
3. Transfer the release using a dedicated SSH key and a pinned VPS host key.
4. On the VPS, serialize operations with a host lock, validate the release,
   load the images and compare their IDs. Re-run preflight and the security gate
   before stopping the API. Never rebuild or silently pull replacement app images.
5. Require an encrypted backup for any existing database volume, then stop the
   API, migrate as the separate migrator role, apply runtime grants and start it.
6. Check internal health/queues, record the current successful release and check
   public HTTPS readiness through Nginx from the GitHub runner.

Deployments use a stable `holyspace-java-prod` Compose project and database volume
across release directories. They do not reload Nginx, issue certificates, bootstrap
administrators or rewrite server secrets. An API maintenance window is expected;
this is not a zero-downtime or automatic schema-rollback deployment.

## One-time VPS setup

First complete the [Ubuntu/Nginx setup](ubuntu-nginx-java-deployment.md), including
DNS, certificates, Docker, private secrets and firewall verification. Python 3.10+
is required for the receiver. Check `docker info --format '{{.Architecture}}'`
reports `x86_64` or `amd64`. The fresh database runtime uses PostgreSQL 18 on Alpine
3.24 with a patched gosu helper. Its current high/critical scan passes; the release
gate still evaluates every exact release. The VPS needs outbound HTTPS for the pinned
scanner image and vulnerability database.

Do not reuse an initialized Debian database directory with this runtime. Existing
unmarked volumes are refused before their container is replaced. For an existing
cluster, use a separately reviewed logical backup/restore migration; for this
fresh deployment, let the pipeline initialize a new volume. Never delete a volume
that contains required data to bypass this check.

Create a dedicated deployment account and directories. Example commands below
are for a new installation; preserve any existing deployment and data:

```sh
sudo adduser --disabled-password --gecos '' holyspace-deploy
sudo usermod -aG docker holyspace-deploy
sudo install -d -o holyspace-deploy -g holyspace-deploy -m 0700 /srv/holyspace
sudo install -d -o holyspace-deploy -g holyspace-deploy -m 0700 \
  /srv/holyspace/incoming /srv/holyspace/releases /srv/holyspace/shared \
  /srv/holyspace/backups
sudo install -d -o root -g root -m 0755 /srv/holyspace/bin
```

Docker access is equivalent to host administration. Treat this SSH credential as
a production administrator credential. Use the account only for trusted releases.
Copy the reviewed receiver from this repository to the VPS administrator's home
directory (for example with SCP). Then install it on the VPS:

```sh
sudo install -o root -g root -m 0755 ~/holyspace-receive-release.py \
  /srv/holyspace/bin/receive-release.py
```

Copy the configured production environment file to
`/srv/holyspace/shared/production.env`, owned by `holyspace-deploy`, mode `0600`.
It contains domain/settings/secret-file paths, not passwords. CI supplies the
release tag; it overrides `RELEASE_TAG` only in each release's private copy.
Use a simple `RELEASE_TAG=...` entry, without shell `export`. Ensure secret files
remain readable by container group 10001 as described in the runbook, and that
the deployment account can read them for preflight. Do not regenerate existing
encryption keys.

Create `/srv/holyspace/shared/backup-recipient`, owned by `holyspace-deploy`, mode
`0600`, containing one age **public recipient**. Keep the corresponding private
identity in independent recovery storage, outside GitHub and release artifacts.
Configure off-site transfer, retention and alerts for `/srv/holyspace/backups`;
the pipeline's local pre-migration backup does not provide off-site recovery.

Generate a dedicated Ed25519 deployment key on a trusted workstation, outside
the repository, for example at `~/.ssh/holyspace-vps-deploy`. This implementation
uses an automation key without a passphrase; protect it in the GitHub production
environment secret and restrict access to that environment. Install
its public key in the account's `~/.ssh/authorized_keys` with `restrict` before
the key (disables forwarding and PTY while permitting the required SSH/SFTP
commands). Keep `.ssh` mode `0700`, `authorized_keys` mode `0600` and correct
ownership. Do not reuse a personal key or send the private key in chat.

Verify SSH connectivity and account Docker access before enabling automation.
Do not expose Docker's socket or API over the network.

## GitHub configuration

In the repository, create an Actions environment named **production**, restrict
deployment branches to `main` and protect that branch with review and required
checks. For fully automatic deployment, do not add a required environment reviewer;
if reviewers are configured, GitHub will wait for their approval.

Add these **production environment secrets**:

| Secret | Value |
| --- | --- |
| `VPS_HOST` | VPS DNS hostname or IPv4 address, without a scheme or SSH username |
| `VPS_USER` | `holyspace-deploy` |
| `VPS_SSH_PORT` | SSH port, usually `22` |
| `VPS_SSH_KEY` | Dedicated private key contents, with actual line breaks |
| `VPS_KNOWN_HOSTS` | Verified OpenSSH known_hosts entry matching that hostname and port |

Obtain the host public key through the provider console or another independently
trusted connection. Compare its fingerprint with
`ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub` on the server. A default-port
entry starts `your-host ssh-ed25519 ...`; for a custom port it starts
`[your-host]:2222 ssh-ed25519 ...`. If using `ssh-keyscan` to collect the entry,
verify its fingerprint independently before saving it. CI never discovers/trusts
a new host key automatically and never disables strict host checking.

Add **repository Actions variable** `PRODUCTION_API_URL`, e.g.
`https://api.your-church.org`. It must match the API origin in the server settings.
Add **repository Actions variable** `PRODUCTION_DEPLOY_ENABLED=true` only after
the host, backups, SSH and launch gates are ready. This switch must be repository
scoped because GitHub evaluates the deploy-job condition before entering its
environment. Leave it absent or `false` while preparing the host.

These controls follow GitHub's documented [environment/deployment rules](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/control-deployments)
and [environment-secret configuration](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets).
Do not put database passwords, encryption keys or provider credentials in the
workflow; they remain mounted secret files on the VPS.

## First run and subsequent releases

Commit and push the reviewed deployment changes to `main` through the normal
review process. Watch **Actions → Java and member app release checks**. The Java
job must pass before `deploy-backend` can start. Deployment is serialized and a
superseded main commit is rejected before transfer. New pushes do not cancel a
running production migration. A manual workflow dispatch on `main` can retry;
each run attempt has a separate immutable release directory.

The first deployment creates a fresh database. Set up the initial administrator
separately using the bootstrap procedure in the production runbook. From the VPS,
use the deployed release and the same project/target:

```sh
cd /srv/holyspace/current
export JAVA_PRODUCTION_TARGET=nginx-api
export JAVA_PRODUCTION_PROJECT=holyspace-java-prod
scripts/java-production/bootstrap.sh administrator admin@your-church.org \
  'Church Administrator' /secure/operator/bootstrap-password
```

Use the runbook's private-password-file permissions and remove that temporary
file after success. CI never creates or resets an administrator password.

The workflow retains the release artifact for seven days. The VPS retains release
directories and old images for operator review/rollback planning; configure disk
capacity alerts and reviewed retention. Do not prune images or bind-mounted
release files used by running containers. A failed transfer can leave files in
`incoming`; remove those only after confirming no deployment is using them.

## Failure and recovery

- A failed test, image scan or backup blocks deployment. Existing API containers
  are not stopped when security or backup checks fail.
- Failed migration leaves the API stopped. Inspect the failed release, DB state
  and service logs on the VPS. Do not force-start against an incomplete schema.
- Failed internal checks leave `current` pointing at the preceding successful
  release, but this does **not** restore its running containers or database.
- Failed public readiness fails the GitHub job. Check DNS, certificates and Nginx;
  the new application may already be healthy internally.
- Do not cancel a running migration. If a runner or connection fails, inspect
  the host lock, release and container state before retrying.
- Application rollback requires confirmation that the previous image supports
  the current schema. Restore a verified backup into a replacement database when
  necessary; do not automatically downgrade migrations or delete volumes.

Host Nginx, Ubuntu patches, certificate renewal, off-site backups, monitoring and
privileged MFA/recovery remain required independently of CI/CD. This repository
change does not create GitHub secrets, enable the repository switch or connect
to a production VPS.
