#!/usr/bin/env python3
"""Isolated topology and live Nginx boundary checks; never starts production services."""
import base64
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import http.client
import json
import os
from pathlib import Path
import secrets
import socket
import ssl
import subprocess
import tempfile
import threading
import time
import unittest
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[2]


def run(*args, **kwargs):
    return subprocess.run(args, capture_output=True, text=True, **kwargs)


def free_port():
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        return listener.getsockname()[1]


class NginxDeploymentChecks(unittest.TestCase):
    def test_preflight_rejects_public_internal_ports(self):
        with tempfile.TemporaryDirectory(prefix="holyspace-nginx-config-") as directory:
            path = Path(directory)
            for name in ("admin_password", "runtime_password", "migration_password"):
                (path / name).write_text(secrets.token_hex(32))
            for name in ("payments_key", "push_key"):
                (path / name).write_text(base64.b64encode(secrets.token_bytes(32)).decode())
            for name in ("smtp_password", "push_access_token"):
                (path / name).touch()
            for secret in path.iterdir():
                secret.chmod(0o600)
            environment = path / "environment"
            environment.write_text(
                "RELEASE_TAG=nginx-verification\nCHURCH_DOMAIN=church.verification.invalid\n"
                "API_DOMAIN=api.verification.invalid\n"
                f"SECRETS_DIR={path}\n"
                "PAYMENTS_PUBLIC_URL=https://church.verification.invalid\n"
                "PAYMENTS_BACKEND_URL=https://api.verification.invalid\n"
                "CORS_ALLOWED_ORIGINS=https://church.verification.invalid\n"
            )
            env = {key: value for key, value in os.environ.items()
                   if not key.startswith("JAVA_PRODUCTION_")}
            env.update(JAVA_PRODUCTION_ENV_FILE=str(environment), JAVA_PRODUCTION_TARGET="nginx-api")
            command = ("python3", str(ROOT / "scripts/java-production/preflight.py"))
            result = run(*command, env=env)
            self.assertEqual(result.returncode, 0, result.stderr)
            resolved = run("bash", "-c", 'source scripts/java-production/common.sh; dc config --format json',
                           env=env, cwd=ROOT, check=True)
            config = json.loads(resolved.stdout)
            self.assertEqual(config["services"]["api"]["ports"][0]["host_ip"], "127.0.0.1")
            self.assertNotIn("edge", config["services"])
            self.assertNotIn("web", config["services"])
            override = path / "unsafe.yaml"
            env["JAVA_PRODUCTION_OVERRIDE_FILE"] = str(override)
            for service, port in (("api", "8082:8082"), ("database", "5432:5432")):
                with self.subTest(service=service):
                    override.write_text(f"services:\n  {service}:\n    ports: !override ['{port}']\n")
                    self.assertNotEqual(run(*command, env=env).returncode, 0)
            env.pop("JAVA_PRODUCTION_OVERRIDE_FILE")
            env["JAVA_PRODUCTION_TARGET"] = "bundled"
            self.assertEqual(run(*command, env=env).returncode, 0)

    def test_live_nginx_routes_and_replaces_untrusted_forwarding_headers(self):
        class Echo(BaseHTTPRequestHandler):
            def do_GET(self):
                payload = json.dumps({"path": self.path, "headers": dict(self.headers)}).encode()
                self.send_response(200)
                self.send_header("Content-Type", "application/json")
                self.send_header("Content-Security-Policy", "default-src 'none'")
                self.end_headers()
                self.wfile.write(payload)

            def log_message(self, *_args):
                pass

        upstream = ThreadingHTTPServer(("127.0.0.1", 0), Echo)
        thread = threading.Thread(target=upstream.serve_forever, daemon=True)
        thread.start()
        container = "holyspace-nginx-check-" + secrets.token_hex(6)
        try:
            with tempfile.TemporaryDirectory(prefix="holyspace-nginx-proxy-") as directory:
                path = Path(directory)
                path.chmod(0o755)
                run("openssl", "req", "-x509", "-newkey", "rsa:2048", "-nodes", "-days", "1",
                    "-subj", "/CN=api.verification.invalid", "-keyout", str(path / "key.pem"),
                    "-out", str(path / "cert.pem"), check=True)
                http_port, https_port = free_port(), free_port()
                site = (ROOT / "docker/java-production/nginx-api.conf").read_text()
                site = site.replace("listen 80;", f"listen 127.0.0.1:{http_port};")
                site = site.replace("listen 443 ssl;", f"listen 127.0.0.1:{https_port} ssl;")
                site = site.replace("listen [::]:80;", "").replace("listen [::]:443 ssl;", "")
                site = site.replace("/etc/letsencrypt/live/api.church.example.org/fullchain.pem", "/test/cert.pem")
                site = site.replace("/etc/letsencrypt/live/api.church.example.org/privkey.pem", "/test/key.pem")
                site = site.replace("127.0.0.1:18082", f"127.0.0.1:{upstream.server_port}")
                site = site.replace("/var/log/nginx/holyspace-api.access.log", "/dev/stdout")
                site = site.replace("/var/log/nginx/holyspace-api.error.log", "/dev/stderr")
                (path / "nginx.conf").write_text("pid /tmp/nginx-check.pid;\nevents {}\nhttp {\n" + site + "\n}\n")
                run("docker", "run", "-d", "--name", container, "--network", "host", "--user", "0",
                    "-v", f"{path}:/test:ro", "--entrypoint", "nginx",
                    os.environ.get("JAVA_NGINX_TEST_IMAGE", "holyspace-java-web:release-check"),
                    "-c", "/test/nginx.conf", "-g", "daemon off;", check=True)
                # Only the disposable test certificate bypasses trust verification.
                context = ssl._create_unverified_context()
                base = f"https://127.0.0.1:{https_port}"
                for attempt in range(50):
                    try:
                        with urllib.request.urlopen(base + "/health/ready", context=context, timeout=2):
                            break
                    except (OSError, urllib.error.URLError):
                        if attempt == 49:
                            self.fail("Temporary Nginx did not become ready.")
                        time.sleep(0.1)
                request = urllib.request.Request(base + "/api/verify?capability=private-test-value", headers={
                    "Forwarded": "host=attacker.invalid;proto=http",
                    "X-Forwarded-Host": "attacker.invalid", "X-Forwarded-Proto": "http",
                    "X-Forwarded-Port": "81", "X-Forwarded-Prefix": "/attack",
                    "X-Forwarded-For": "203.0.113.100",
                })
                with urllib.request.urlopen(request, context=context) as response:
                    payload = json.load(response)
                    self.assertEqual(response.headers["Content-Security-Policy"], "default-src 'none'")
                headers = {key.lower(): value for key, value in payload["headers"].items()}
                self.assertEqual(headers["host"], "api.church.example.org")
                self.assertEqual(headers["x-forwarded-host"], "api.church.example.org")
                self.assertEqual(headers["x-forwarded-proto"], "https")
                self.assertEqual(headers["x-forwarded-for"], "127.0.0.1")
                for key in ("forwarded", "x-forwarded-port", "x-forwarded-prefix"):
                    self.assertNotIn(key, headers)
                with urllib.request.urlopen(base + "/health/ready", context=context) as response:
                    self.assertEqual(json.load(response)["path"], "/actuator/health/readiness")
                for route in ("/docs", "/v3/api-docs", "/actuator/env"):
                    with self.assertRaises(urllib.error.HTTPError) as error:
                        urllib.request.urlopen(base + route, context=context)
                    self.assertEqual(error.exception.code, 404)
                    error.exception.close()
                redirect = http.client.HTTPConnection("127.0.0.1", http_port)
                redirect.request("GET", "/api/verify?test=1", headers={"Host": "attacker.invalid"})
                response = redirect.getresponse()
                self.assertEqual(response.status, 308)
                self.assertEqual(response.getheader("Location"), "https://api.church.example.org/api/verify?test=1")
                redirect.close()
                # Headers alone must trigger the size rejection before a body is sent.
                connection = http.client.HTTPSConnection("127.0.0.1", https_port, context=context)
                connection.putrequest("POST", "/api/verify")
                connection.putheader("Content-Length", str(6 * 1024 * 1024 + 1))
                connection.endheaders()
                self.assertEqual(connection.getresponse().status, 413)
                connection.close()
                logs = run("docker", "logs", container, check=True)
                self.assertNotIn("private-test-value", logs.stdout + logs.stderr)
        finally:
            run("docker", "rm", "-f", container)
            upstream.shutdown()
            upstream.server_close()
            thread.join(timeout=5)


if __name__ == "__main__":
    unittest.main()
