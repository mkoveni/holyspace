#!/usr/bin/env python3
"""Interactive Ubuntu VPS and GitHub Actions setup. Run on the VPS with sudo."""
import argparse
import base64
from dataclasses import asdict, dataclass
import hashlib
import ipaddress
import json
import os
from pathlib import Path
import platform
import pwd
import re
import secrets
import shutil
import socket
import subprocess
import sys
import tempfile
from urllib.request import urlopen
import zipfile

ROOT = Path(__file__).resolve().parents[2]
ACCOUNT = "holyspace-deploy"
INSTALL = Path("/srv/holyspace")
PRIVATE = Path("/root/holyspace-setup")
STATE = PRIVATE / "settings.json"
SITE = Path("/etc/nginx/sites-available/holyspace-api")
ENABLED_SITE = Path("/etc/nginx/sites-enabled/holyspace-api")
MARKER = "# Managed by Holyspace VPS setup.\n"
ASSETS = ("docker/java-production/nginx-api.conf", "scripts/java-production/receive-release.py",
          ".env.java-production.example")


def asset(name):
    archive = Path(sys.argv[0])
    if archive.is_file() and zipfile.is_zipfile(archive):
        with zipfile.ZipFile(archive) as bundle:
            return bundle.read(name).decode()
    return (ROOT / name).read_text()


def bundle_setup(output):
    # A single portable file for the VPS; no repository checkout or secrets needed.
    with zipfile.ZipFile(output, "x", compression=zipfile.ZIP_DEFLATED) as bundle:
        bundle.writestr("__main__.py", Path(__file__).read_text())
        for name in ASSETS:
            bundle.writestr(name, asset(name))
    print("Created standalone VPS installer: " + str(output))


@dataclass(frozen=True)
class Settings:
    api_domain: str
    frontend_domain: str
    email: str
    repository: str
    ssh_host: str
    ssh_port: int
    backup_recipient: str = ""

    def validate(self):
        for domain in (self.api_domain, self.frontend_domain):
            if (len(domain) > 253 or "." not in domain or not all(
                    re.fullmatch(r"[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?", label)
                    for label in domain.split(".")) or domain.endswith((".local", ".test", ".invalid"))
                    or domain in ("church.example.org", "api.church.example.org")):
                raise ValueError("Use real public DNS names without https://, paths or ports.")
        if self.api_domain == self.frontend_domain:
            raise ValueError("Frontend and API domains must differ.")
        if not re.fullmatch(r"[^\s@]+@[^\s@]+\.[^\s@]+", self.email):
            raise ValueError("Provide a valid certificate contact email.")
        if not re.fullmatch(r"[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+", self.repository):
            raise ValueError("Use the GitHub repository format owner/repository.")
        if any(part in (".", "..") for part in self.repository.split("/")):
            raise ValueError("Use actual GitHub owner and repository names.")
        if not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9.-]*", self.ssh_host):
            raise ValueError("SSH host must be a public DNS hostname or IPv4 address.")
        if not 1 <= self.ssh_port <= 65535:
            raise ValueError("SSH port must be between 1 and 65535.")
        if self.backup_recipient and not re.fullmatch(r"age1[0-9a-z]{58}", self.backup_recipient):
            raise ValueError("Use a single age public recipient, or leave it blank to generate a recovery key.")
        return self


def run(*args, **kwargs):
    return subprocess.run(args, check=True, text=True, **kwargs)


def protected_write(path, content, mode=0o600, uid=0, gid=0, preserve=False):
    """Never follow links; exclusive creation is used for irreplaceable secrets."""
    if path.is_symlink():
        raise ValueError("Refusing a symlink at " + str(path))
    if preserve and path.exists():
        return False
    if preserve:
        descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, mode)
        with os.fdopen(descriptor, "wb" if isinstance(content, bytes) else "w") as stream:
            stream.write(content)
    else:
        descriptor, temporary = tempfile.mkstemp(prefix=".holyspace-", dir=path.parent)
        try:
            with os.fdopen(descriptor, "wb" if isinstance(content, bytes) else "w") as stream:
                stream.write(content)
            os.chmod(temporary, mode)
            os.chown(temporary, uid, gid)
            os.replace(temporary, path)
        finally:
            Path(temporary).unlink(missing_ok=True)
    os.chmod(path, mode)
    os.chown(path, uid, gid)
    return True


def nginx_config(domain, tls=True):
    source = asset("docker/java-production/nginx-api.conf")
    source = source.replace("api.church.example.org", domain)
    if not tls:
        source = source.split("\nserver {\n    listen 443 ssl;", 1)[0]
    return MARKER + source


def validate_platform(release, version, machine):
    if (release.get("ID", "").strip('"') != "ubuntu"
            or release.get("VERSION_ID", "").strip('"') not in ("22.04", "24.04", "26.04", "26.10")
            or version < (3, 10) or machine not in ("x86_64", "amd64")):
        raise ValueError("This installer requires AMD64 Ubuntu 22.04/24.04/26.04/26.10 with Python 3.10+.")


def check_host(settings):
    if os.geteuid() != 0:
        raise ValueError("Run this setup on the Ubuntu VPS with sudo, or use --plan for a preview.")
    release = dict(line.split("=", 1) for line in Path("/etc/os-release").read_text().splitlines()
                   if "=" in line)
    validate_platform(release, sys.version_info[:2], platform.machine())
    # Fail before changing the host if this domain is already served by another site.
    for site in Path("/etc/nginx/sites-enabled").glob("*"):
        if site.name != ENABLED_SITE.name and site.is_file():
            content = re.sub(r"#.*", "", site.read_text())
            declarations = re.findall(r"\bserver_name\s+([^;]+);", content)
            if any(settings.api_domain in declaration.split() for declaration in declarations):
                raise ValueError("The API domain is already configured in another Nginx site: " + str(site))
    api_addresses = {entry[4][0] for entry in socket.getaddrinfo(settings.api_domain, 80)}
    host_addresses = {entry[4][0] for entry in socket.getaddrinfo(settings.ssh_host, settings.ssh_port)}
    # Include global addresses assigned directly to this VPS for valid IPv6 DNS records.
    if shutil.which("ip"):
        interfaces = json.loads(subprocess.check_output(["ip", "-json", "address"], text=True))
        host_addresses |= {item["local"] for interface in interfaces for item in interface.get("addr_info", [])
                           if item.get("scope") == "global"}
    if not api_addresses or not api_addresses <= host_addresses or not all(
            ipaddress.ip_address(address).is_global for address in api_addresses):
        raise ValueError("Point every API A/AAAA record directly at this VPS before setup; CDN proxying is not supported here.")
    return release.get("VERSION_CODENAME", "").strip('"')


def github_cli_ready():
    for command, option in ((["gh", "auth", "login", "--help"], "--insecure-storage"),
                            (["gh", "variable", "set", "--help"], "--body"),
                            (["gh", "secret", "set", "--help"], "--env")):
        if not shutil.which("gh"):
            return False
        result = subprocess.run(command, capture_output=True, text=True)
        if result.returncode or option not in result.stdout:
            return False
    return True


def install_github_cli(env):
    # Use the maintained upstream package; distro clients can also target retired APIs.
    keyring = Path("/etc/apt/keyrings")
    keyring.mkdir(mode=0o755, exist_ok=True)
    keyring.chmod(0o755)
    existing = [path for path in Path("/etc/apt/sources.list.d").glob("*") if path.is_file()
                and "cli.github.com/packages" in path.read_text()]
    if not existing:
        with urlopen("https://cli.github.com/packages/githubcli-archive-keyring.gpg", timeout=30) as response:
            key = response.read()
        protected_write(keyring / "githubcli-archive-keyring.gpg", key, mode=0o644)
        protected_write(Path("/etc/apt/sources.list.d/holyspace-github-cli.sources"),
                        "Types: deb\nURIs: https://cli.github.com/packages\nSuites: stable\n"
                        "Components: main\nArchitectures: amd64\n"
                        "Signed-By: /etc/apt/keyrings/githubcli-archive-keyring.gpg\n", mode=0o644)
    run("apt-get", "update", env=env)
    run("apt-get", "install", "-y", "gh", env=env)
    if not github_cli_ready():
        raise ValueError("The gh executable in PATH lacks required features; use the official /usr/bin/gh package.")


def install_packages(codename):
    print("Installing required Ubuntu packages...")
    env = {**os.environ, "DEBIAN_FRONTEND": "noninteractive"}
    run("apt-get", "update", env=env)
    run("apt-get", "-o", "Dpkg::Options::=--force-confdef", "-o", "Dpkg::Options::=--force-confold",
        "install", "-y", "ca-certificates", "curl", "nginx", "certbot", "age",
        "openssl", "openssh-server", env=env)
    install_github_cli(env)
    if not shutil.which("docker"):
        keyring = Path("/etc/apt/keyrings")
        keyring.mkdir(mode=0o755, exist_ok=True)
        keyring.chmod(0o755)
        existing = [path for path in Path("/etc/apt/sources.list.d").glob("*") if path.is_file()
                    and "download.docker.com" in path.read_text()]
        if not existing:
            with urlopen("https://download.docker.com/linux/ubuntu/gpg", timeout=30) as response:
                key = response.read().decode()
            protected_write(keyring / "docker.asc", key, mode=0o644)
            protected_write(Path("/etc/apt/sources.list.d/holyspace-docker.sources"),
                            "Types: deb\nURIs: https://download.docker.com/linux/ubuntu\n"
                            f"Suites: {codename}\nComponents: stable\nArchitectures: amd64\n"
                            "Signed-By: /etc/apt/keyrings/docker.asc\n", mode=0o644)
        run("apt-get", "update", env=env)
        run("apt-get", "install", "-y", "docker-ce", "docker-ce-cli", "containerd.io",
            "docker-buildx-plugin", "docker-compose-plugin", env=env)
    version = subprocess.check_output(["docker", "compose", "version", "--short"], text=True)
    numbers = re.search(r"(\d+)\.(\d+)\.(\d+)", version)
    if not numbers or tuple(map(int, numbers.groups())) < (2, 24, 4):
        raise ValueError("Update the existing Docker Compose plugin to 2.24.4+ before rerunning setup.")
    run("systemctl", "enable", "--now", "docker", "nginx")


def prepare_account():
    try:
        account = pwd.getpwnam(ACCOUNT)
    except KeyError:
        run("useradd", "--create-home", "--shell", "/bin/bash", ACCOUNT)
        account = pwd.getpwnam(ACCOUNT)
    if account.pw_dir != "/home/" + ACCOUNT or account.pw_uid == 0:
        raise ValueError("The existing deployment account has an unexpected home or UID.")
    run("usermod", "--append", "--groups", "docker", ACCOUNT)
    for path in (INSTALL, INSTALL / "incoming", INSTALL / "releases", INSTALL / "shared",
                 INSTALL / "backups", INSTALL / "secrets"):
        if path.is_symlink():
            raise ValueError("Refusing a symlinked installation directory.")
        path.mkdir(mode=0o700, parents=True, exist_ok=True)
        os.chown(path, account.pw_uid, account.pw_gid)
        path.chmod(0o700)
    if (INSTALL / "bin").is_symlink():
        raise ValueError("Installation bin directory must not be a symlink.")
    (INSTALL / "bin").mkdir(mode=0o755, exist_ok=True)
    (INSTALL / "bin").chmod(0o755)
    os.chown(INSTALL / "bin", 0, 0)
    protected_write(INSTALL / "bin/receive-release.py",
                    asset("scripts/java-production/receive-release.py"), mode=0o755)
    return account


def create_secrets(directory, existing_installation=False):
    names = ("admin_password", "runtime_password", "migration_password", "payments_key", "push_key")
    if existing_installation and any(not (directory / name).is_file() for name in names):
        raise ValueError("Existing deployment is missing secrets. Recover its original keys/passwords; do not regenerate them.")
    for name in (*names, "smtp_password", "push_access_token"):
        path = directory / name
        if path.is_symlink():
            raise ValueError("Secret files must not be symlinks.")
        if not path.exists():
            value = (base64.b64encode(secrets.token_bytes(32)).decode() if name.endswith("_key")
                     else secrets.token_hex(32) if name in names else "")
            # Ownership is assigned by the caller once all files have been checked.
            protected_write(path, value + ("\n" if value else ""), preserve=True)
        if name in names and not path.read_text().strip():
            raise ValueError("An existing required secret is empty; restore it before continuing.")


def configure_files(settings, account):
    directory = INSTALL / "secrets"
    volume = subprocess.run(["docker", "volume", "inspect", "holyspace-java-prod_database"],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode == 0
    create_secrets(directory, existing_installation=volume or (INSTALL / "current").exists())
    for secret in directory.iterdir():
        if secret.is_file() and not secret.is_symlink():
            os.chown(secret, account.pw_uid, 10001)
            secret.chmod(0o640)
    environment = INSTALL / "shared/production.env"
    if environment.is_symlink():
        raise ValueError("Production environment file must not be a symlink.")
    if environment.exists():
        values = dict(line.split("=", 1) for line in environment.read_text().splitlines()
                      if "=" in line and not line.lstrip().startswith("#"))
        if (values.get("PAYMENTS_PUBLIC_URL") != "https://" + settings.frontend_domain
                or values.get("PAYMENTS_BACKEND_URL") != "https://" + settings.api_domain
                or values.get("SECRETS_DIR") != str(directory)):
            raise ValueError("Existing production settings differ; plan domain/secret changes separately.")
    else:
        content = asset(".env.java-production.example")
        content = content.replace("api.church.example.org", settings.api_domain)
        content = content.replace("church.example.org", settings.frontend_domain)
        protected_write(environment, content, uid=account.pw_uid, gid=account.pw_gid, preserve=True)
    recipient_file = INSTALL / "shared/backup-recipient"
    if recipient_file.is_symlink():
        raise ValueError("Backup recipient file must not be a symlink.")
    if not recipient_file.exists():
        recipient = settings.backup_recipient
        if not recipient:
            identity = PRIVATE / "backup-identity.txt"
            if not identity.exists():
                run("age-keygen", "-o", str(identity), stderr=subprocess.DEVNULL)
                identity.chmod(0o600)
            recipient = subprocess.check_output(["age-keygen", "-y", str(identity)], text=True).strip()
        protected_write(recipient_file, recipient + "\n", uid=account.pw_uid, gid=account.pw_gid, preserve=True)
    elif settings.backup_recipient and recipient_file.read_text().strip() != settings.backup_recipient:
        raise ValueError("The existing backup recipient differs; recovery-key changes require a separate plan.")
    run("age", "--encrypt", "--recipient", recipient_file.read_text().strip(), input="",
        stdout=subprocess.DEVNULL)


def install_site(settings):
    hash_file = PRIVATE / "nginx-site.sha256"
    if SITE.exists():
        current = SITE.read_text()
        if (not current.startswith(MARKER) or not hash_file.exists()
                or hashlib.sha256(current.encode()).hexdigest() != hash_file.read_text().strip()):
            raise ValueError("Existing Nginx site is not managed by this script or has manual edits; preserve and review it separately.")
    if ENABLED_SITE.exists() or ENABLED_SITE.is_symlink():
        if not ENABLED_SITE.is_symlink() or ENABLED_SITE.resolve() != SITE:
            raise ValueError("The Nginx enabled-site path is already used by another configuration.")
    certificate = Path("/etc/letsencrypt/live") / settings.api_domain / "fullchain.pem"

    def apply(tls):
        old = SITE.read_text() if SITE.exists() else None
        linked = ENABLED_SITE.is_symlink()
        content = nginx_config(settings.api_domain, tls)
        protected_write(SITE, content, mode=0o644)
        if not linked:
            ENABLED_SITE.symlink_to(SITE)
        try:
            run("nginx", "-t")
            run("systemctl", "reload", "nginx")
        except subprocess.CalledProcessError:
            if old is None:
                SITE.unlink(missing_ok=True)
            else:
                protected_write(SITE, old, mode=0o644)
            if not linked:
                ENABLED_SITE.unlink(missing_ok=True)
            raise
        protected_write(hash_file, hashlib.sha256(content.encode()).hexdigest() + "\n")

    Path("/var/www/letsencrypt").mkdir(parents=True, exist_ok=True, mode=0o755)
    Path("/var/www/letsencrypt").chmod(0o755)
    apply(tls=certificate.exists())
    run("certbot", "certonly", "--webroot", "-w", "/var/www/letsencrypt", "--non-interactive",
        "--agree-tos", "--email", settings.email, "--cert-name", settings.api_domain,
        "-d", settings.api_domain, "--keep-until-expiring")
    apply(tls=True)
    hook = Path("/etc/letsencrypt/renewal-hooks/deploy/holyspace-nginx")
    hook.parent.mkdir(parents=True, exist_ok=True)
    hook_content = "#!/bin/sh\n# Managed by Holyspace VPS setup.\nset -eu\nnginx -t\nsystemctl reload nginx\n"
    if hook.exists() and hook.read_text() != hook_content:
        raise ValueError("An existing certificate renewal hook has custom content; preserve and review it separately.")
    protected_write(hook, hook_content, mode=0o755)
    run("systemctl", "enable", "--now", "certbot.timer")
    run("certbot", "renew", "--dry-run", "--cert-name", settings.api_domain)


def deployment_key(account):
    key = PRIVATE / "github-actions-deploy"
    if key.is_symlink():
        raise ValueError("Deployment private key must not be a symlink.")
    if not key.exists():
        run("ssh-keygen", "-q", "-t", "ed25519", "-N", "", "-C", "holyspace-github-actions", "-f", str(key))
    key.chmod(0o600)
    public = subprocess.check_output(["ssh-keygen", "-y", "-P", "", "-f", str(key)], text=True).strip()
    ssh = Path(account.pw_dir) / ".ssh"
    if ssh.is_symlink():
        raise ValueError("Deployment .ssh directory must not be a symlink.")
    ssh.mkdir(mode=0o700, exist_ok=True)
    os.chown(ssh, account.pw_uid, account.pw_gid)
    ssh.chmod(0o700)
    authorized = ssh / "authorized_keys"
    if authorized.is_symlink():
        raise ValueError("Deployment authorized_keys must not be a symlink.")
    existing = authorized.read_text() if authorized.exists() else ""
    if public.split()[1] not in existing:
        protected_write(authorized, existing.rstrip() + ("\n" if existing else "") + "restrict " + public + "\n",
                        uid=account.pw_uid, gid=account.pw_gid)
    return key


def github_settings(settings, key):
    host_public = Path("/etc/ssh/ssh_host_ed25519_key.pub").read_text().split()
    if len(host_public) < 2 or host_public[0] != "ssh-ed25519":
        raise ValueError("The VPS needs an Ed25519 SSH host key.")
    host = settings.ssh_host if settings.ssh_port == 22 else f"[{settings.ssh_host}]:{settings.ssh_port}"
    return {"VPS_HOST": settings.ssh_host, "VPS_USER": ACCOUNT, "VPS_SSH_PORT": str(settings.ssh_port),
            "VPS_KNOWN_HOSTS": host + " " + " ".join(host_public[:2]), "VPS_SSH_KEY": key.read_text()}


def configure_github(settings, values):
    print("Configuring GitHub. Complete the browser login when prompted...")
    with tempfile.TemporaryDirectory(prefix="gh-login-", dir=PRIVATE) as directory:
        env = {**os.environ, "GH_CONFIG_DIR": directory, "GH_PROMPT_DISABLED": "1"}
        if not (env.get("GH_TOKEN") or env.get("GITHUB_TOKEN")):
            # Login is interactive, but credentials live only in this temporary directory.
            run("gh", "auth", "login", "--hostname", "github.com", "--git-protocol", "https", "--web",
                "--insecure-storage", env=env)
        repo = "repos/" + settings.repository

        def api(path, method="GET", body=None):
            command = ["gh", "api", "--method", method, path]
            if body is not None:
                command += ["--input", "-"]
            return subprocess.run(command, input=json.dumps(body) if body is not None else None,
                                  env=env, text=True, capture_output=True)

        info = api(repo)
        if info.returncode or not json.loads(info.stdout).get("permissions", {}).get("admin"):
            raise ValueError("GitHub repository administrator access is required to configure deployment.")
        if json.loads(info.stdout)["default_branch"] != "main":
            raise ValueError("This workflow targets main; update it before configuring another default branch.")
        environment = api(repo + "/environments/production")
        if environment.returncode:
            if "HTTP 404" not in environment.stderr:
                raise ValueError("Cannot read the GitHub production environment.")
            created = api(repo + "/environments/production", "PUT", {
                "deployment_branch_policy": {"protected_branches": False, "custom_branch_policies": True}})
            if created.returncode:
                raise ValueError("Cannot create the GitHub production environment; check access and plan support.")
            policy = api(repo + "/environments/production/deployment-branch-policies", "POST", {"name": "main", "type": "branch"})
            if policy.returncode:
                raise ValueError("Cannot restrict the production environment to main.")
        else:
            print("Preserving the existing production environment's approval and branch rules.")
        for name, value in values.items():
            run("gh", "secret", "set", name, "--repo", settings.repository, "--env", "production",
                input=value, env=env, stdout=subprocess.DEVNULL)
        for name, value in (("PRODUCTION_API_URL", "https://" + settings.api_domain),
                            ("PRODUCTION_DEPLOY_ENABLED", "true")):
            run("gh", "variable", "set", name, "--repo", settings.repository, "--body", value,
                env=env, stdout=subprocess.DEVNULL)


def collect(args):
    previous = json.loads(STATE.read_text()) if os.geteuid() == 0 and STATE.exists() else {}

    def value(name, prompt, default=""):
        supplied = getattr(args, name, None)
        if supplied is not None:
            return supplied
        fallback = previous.get(name, default)
        if args.plan:
            if fallback or name in ("ssh_port", "backup_recipient"):
                return fallback
            raise ValueError("--plan requires --" + name.replace("_", "-") + ".")
        answer = input(prompt + (f" [{fallback}]" if fallback else "") + ": ").strip()
        return answer or fallback

    return Settings(value("api_domain", "API domain").lower(),
                    value("frontend_domain", "Frontend domain").lower(),
                    value("email", "Certificate contact email"),
                    value("repository", "GitHub owner/repository"),
                    value("ssh_host", "Public VPS SSH hostname or IPv4 address"),
                    int(value("ssh_port", "SSH port", "22")),
                    value("backup_recipient", "Age public backup recipient (blank generates a recovery key)", "")).validate()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for option in ("api-domain", "frontend-domain", "email", "repository", "ssh-host", "ssh-port", "backup-recipient"):
        parser.add_argument("--" + option)
    parser.add_argument("--bundle", type=Path, metavar="FILE.pyz", help="Build a standalone installer to copy to the VPS.")
    parser.add_argument("--plan", action="store_true", help="Validate supplied settings and show actions without changing anything.")
    parser.add_argument("--skip-github", action="store_true", help="Prepare the host and save private GitHub setup files for later.")
    args = parser.parse_args()
    if args.bundle:
        bundle_setup(args.bundle)
        return
    settings = collect(args)
    if args.plan:
        print(json.dumps(asdict(settings), indent=2))
        print("Plan: install dependencies; prepare account/secrets/receiver; configure Nginx/TLS/renewal; "
              + ("save GitHub setup files." if args.skip_github else "configure GitHub production secrets and enable deployment."))
        return
    codename = check_host(settings)
    os.umask(0o077)
    if PRIVATE.is_symlink():
        raise ValueError("The root setup directory must not be a symlink.")
    PRIVATE.mkdir(mode=0o700, exist_ok=True)
    PRIVATE.chmod(0o700)
    # Save non-secret prompt defaults early so a failed certificate/login step can be resumed.
    protected_write(STATE, json.dumps(asdict(settings), indent=2) + "\n")
    install_packages(codename)
    account = prepare_account()
    configure_files(settings, account)
    install_site(settings)
    key = deployment_key(account)
    values = github_settings(settings, key)
    # This file contains a private key: never print it, commit it or include it in releases.
    protected_write(PRIVATE / "github-secrets.json", json.dumps(values, indent=2) + "\n")
    if not args.skip_github:
        configure_github(settings, values)
    print("VPS setup complete. No application deployment or database initialization was performed.")
    print("GitHub settings " + ("saved privately for manual configuration." if args.skip_github else "configured; the next passing main release can deploy automatically."))
    print("Protect /root/holyspace-setup; copy any generated backup-identity.txt to independent recovery storage.")
    print("Existing image security gates, off-site backups, firewall review and production acceptance still apply.")


if __name__ == "__main__":
    try:
        main()
    except ValueError as error:
        print("Setup stopped: " + str(error), file=sys.stderr)
        sys.exit(1)
    except (KeyError, OSError, subprocess.CalledProcessError):
        # Child commands may name private files; avoid dumping their arguments or configuration.
        print("Setup stopped. Check the preceding stage output and configuration; existing secrets were preserved. "
              "See docs/backend-cicd.md for prerequisites and rerun instructions.", file=sys.stderr)
        sys.exit(1)
