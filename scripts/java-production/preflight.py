#!/usr/bin/env python3
"""Validate resolved Compose configuration without printing private values."""
import base64
import ipaddress
import json
import os
from pathlib import Path
import stat
import subprocess
import sys
from urllib.parse import urlsplit
import uuid

root = Path(__file__).resolve().parents[2]
env = os.environ.get("JAVA_PRODUCTION_ENV_FILE", str(root / ".env.java-production.local"))
command = ["docker", "compose", "--env-file", env, "-f", str(root / "compose.java-production.yaml")]
target = os.environ.get("JAVA_PRODUCTION_TARGET", "bundled")
if target == "nginx-api":
    command += ["-f", str(root / "compose.java-production-nginx.yaml")]
elif target == "nginx-app":
    command += ["-f", str(root / "compose.java-production-nginx-app.yaml")]
if os.environ.get("JAVA_PRODUCTION_OVERRIDE_FILE"):
    command += ["-f", os.environ["JAVA_PRODUCTION_OVERRIDE_FILE"]]
if os.environ.get("JAVA_PRODUCTION_PROJECT"):
    command += ["-p", os.environ["JAVA_PRODUCTION_PROJECT"]]


def require(condition, message):
    if not condition:
        raise ValueError(message)


def origin(value):
    parsed = urlsplit(value)
    require(parsed.scheme == "https" and parsed.hostname and not parsed.username
            and not parsed.password and parsed.path in ("", "/") and not parsed.query
            and not parsed.fragment and parsed.port in (None, 443), "Public URLs must be HTTPS origins.")
    require(parsed.hostname not in ("localhost", "church.example.org", "api.church.example.org")
            and not parsed.hostname.endswith((".test", ".local")), "Replace placeholder/local public domains.")
    try:
        address = ipaddress.ip_address(parsed.hostname)
    except ValueError:
        address = None
    require(address is None or address.is_global, "Public origins cannot use private IP addresses.")
    return value.rstrip("/")


try:
    require(target in ("bundled", "nginx-api", "nginx-app"), "Unknown JAVA_PRODUCTION_TARGET.")
    result = subprocess.run(command + ["config", "--format", "json"], capture_output=True, text=True)
    require(result.returncode == 0, "Compose configuration is incomplete; check the private environment file.")
    config = json.loads(result.stdout)
    api = config["services"]["api"]
    settings = api["environment"]
    release = api["image"].rsplit(":", 1)[-1]
    require(release not in ("latest", "local", "replace-with-git-commit"), "Choose an immutable release tag.")
    frontend = origin(settings["PAYMENTS_PUBLIC_URL"])
    backend = origin(settings["PAYMENTS_BACKEND_URL"])
    require(frontend != backend, "Use separate frontend and API origins.")
    if target == "bundled":
        edge = config["services"]["edge"]["environment"]
        require(frontend == "https://" + edge["CHURCH_DOMAIN"] and backend == "https://" + edge["API_DOMAIN"],
                "Public origins must match their HTTPS routing domains.")
    origins = [origin(value.strip()) for value in settings["CORS_ALLOWED_ORIGINS"].split(",")]
    require(frontend in origins, "CORS must include the frontend origin.")
    require(not config["services"]["database"].get("ports"), "Database must not publish production ports.")
    if target != "nginx-app":
        require(not config["services"].get("web", {}).get("ports"), "Web container must not publish production ports.")
    api_ports = api.get("ports", [])
    if target in ("nginx-api", "nginx-app"):
        require(len(api_ports) == 1 and api_ports[0].get("host_ip") == "127.0.0.1"
                and str(api_ports[0].get("published")) == "18082"
                and api_ports[0].get("target") == 8082
                and api_ports[0].get("protocol", "tcp") == "tcp",
                "Host Nginx requires exactly 127.0.0.1:18082 mapped to API port 8082.")
        require("edge" not in config["services"] and (target == "nginx-app" or "web" not in config["services"]),
                "Host Nginx mode must not enable bundled-edge profiles.")
        if target == "nginx-app":
            web_ports = config["services"]["web"].get("ports", [])
            require(len(web_ports) == 1 and web_ports[0].get("host_ip") == "127.0.0.1"
                    and str(web_ports[0].get("published")) == "18080"
                    and web_ports[0].get("target") == 8080
                    and web_ports[0].get("protocol", "tcp") == "tcp",
                    "Host Nginx requires exactly 127.0.0.1:18080 mapped to web port 8080.")
    else:
        require(not api_ports, "Only the HTTPS edge may publish production ports.")
    values = {}
    for name, secret in config["secrets"].items():
        path = Path(secret["file"])
        require(path.is_file() and not path.is_symlink(), "Missing or symlinked secret file: " + name)
        require(stat.S_IMODE(path.stat().st_mode) & 0o007 == 0, "Secret must not be world-accessible: " + name)
        values[name] = path.read_text().strip()
    for name in ("admin_password", "runtime_password", "migration_password"):
        require(len(values[name]) >= 24, "Database passwords must have at least 24 characters: " + name)
    require(len({values[name] for name in ("admin_password", "runtime_password", "migration_password")}) == 3,
            "Database roles need different passwords.")
    for name in ("payments_key", "push_key"):
        require(len(base64.b64decode(values[name], validate=True)) == 32, "Encryption keys must decode to 32 bytes: " + name)
    require(values["payments_key"] != values["push_key"], "Payments and push need separate encryption keys.")
    if str(settings["EMAIL_ENABLED"]).lower() == "true":
        require(settings["SMTP_HOST"] and settings["EMAIL_FROM"] and settings["SMTP_USERNAME"]
                and values["smtp_password"], "Complete production SMTP configuration first.")
        require(str(settings["SMTP_STARTTLS"]).lower() == "true", "Production SMTP requires STARTTLS.")
    if str(settings["PUSH_ENABLED"]).lower() == "true":
        uuid.UUID(settings["PUSH_PROJECT_ID"])
    print("Production preflight passed. Outbound providers still require live acceptance checks.")
except (ValueError, KeyError, OSError, TypeError) as error:
    # Never print provider/secret values or resolved Compose configuration.
    print("Production preflight failed: " + (str(error) if isinstance(error, ValueError) else "Cannot validate configuration/files."), file=sys.stderr)
    sys.exit(1)
