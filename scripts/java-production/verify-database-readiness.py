#!/usr/bin/env python3
"""Reproduce PostgreSQL initialization races in an isolated disposable container."""
import argparse
from pathlib import Path
import secrets
import subprocess
import tempfile
import time


def run(*args, **kwargs):
    return subprocess.run(args, capture_output=True, text=True, **kwargs)


def wait_for(predicate):
    deadline = time.monotonic() + 90
    while time.monotonic() < deadline:
        if predicate():
            return
        time.sleep(0.2)
    raise RuntimeError("Disposable database did not reach the expected initialization stage.")


def verify(image):
    container = "holyspace-db-ready-" + secrets.token_hex(6)
    try:
        with tempfile.TemporaryDirectory(prefix="holyspace-db-ready-") as directory:
            fixture = Path(directory) / "delayed-init.sh"
            fixture.write_text('''#!/usr/bin/env bash
set -euo pipefail
source /usr/local/bin/docker-entrypoint.sh
# Delay creation after the temporary socket server is accepting connections.
original=$(declare -f docker_setup_db)
eval "${original/docker_setup_db/original_setup_db}"
docker_setup_db() {
  while [[ ! -f /tmp/allow-database-creation ]]; do sleep 0.1; done
  original_setup_db
}
_main postgres
''')
            fixture.chmod(0o644)
            # The fixture bypasses the root re-exec so the deliberate delay survives.
            run("docker", "run", "-d", "--rm", "--name", container, "--network", "none", "--user", "postgres",
                "-e", "POSTGRES_DB=holyspace", "-e", "POSTGRES_USER=postgres",
                "-e", "POSTGRES_PASSWORD=" + secrets.token_hex(32),
                "-v", str(fixture) + ":/tmp/delayed-init.sh:ro", "--entrypoint", "bash",
                image, "/tmp/delayed-init.sh", check=True)

            def execute(*args):
                return run("docker", "exec", container, *args)

            wait_for(lambda: execute("pg_isready", "-U", "postgres", "-d", "holyspace").returncode == 0)
            missing = execute("psql", "-U", "postgres", "-d", "postgres", "-Atc",
                              "SELECT count(*) FROM pg_database WHERE datname = 'holyspace'")
            if missing.returncode or missing.stdout.strip() != "0":
                raise RuntimeError("Fixture did not reproduce the missing database during initialization.")
            if execute("holyspace-database-ready").returncode == 0:
                raise RuntimeError("Readiness accepted the temporary initialization server.")
            execute("touch", "/tmp/allow-database-creation").check_returncode()
            wait_for(lambda: execute("holyspace-database-ready").returncode == 0)
            # A running final server also must not report healthy for a missing DB.
            execute("psql", "-U", "postgres", "-d", "postgres", "-v", "ON_ERROR_STOP=1",
                    "-c", "DROP DATABASE holyspace").check_returncode()
            if execute("pg_isready", "-h", "127.0.0.1", "-U", "postgres", "-d", "holyspace").returncode:
                raise RuntimeError("Fixture did not reproduce pg_isready's missing-database false positive.")
            if execute("holyspace-database-ready").returncode == 0:
                raise RuntimeError("Readiness accepted a missing application database.")
            print("Database readiness passed: delayed creation and missing databases are rejected; completed initialization is accepted.")
    finally:
        run("docker", "rm", "-f", "-v", container)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--image", default="holyspace-java-database:release-check")
    verify(parser.parse_args().image)
