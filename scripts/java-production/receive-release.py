#!/usr/bin/env python3
"""Install once on the VPS; receive a trusted CI release without rebuilding it."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile

COMPONENTS = ("api", "database", "tools")


def digest(path):
    checksum = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            checksum.update(chunk)
    return checksum.hexdigest()


def unpack(archive, destination, commit):
    with tarfile.open(archive, "r:") as bundle:
        seen = set()
        for member in bundle.getmembers():
            path = Path(member.name)
            allowed = (member.name in ("manifest.json", "images.tar.gz", "compose.java-production.yaml",
                                       "compose.java-production-nginx.yaml")
                       or (str(path.parent) in ("scripts/java-production", "docker/java-production")))
            if (member.type not in (tarfile.REGTYPE, tarfile.AREGTYPE) or not allowed
                    or path.is_absolute() or ".." in path.parts
                    or member.name in seen):
                raise ValueError("Release archive contains an unexpected entry.")
            seen.add(member.name)
        # Explicit regular-file copying works with stock Python 3.10, including
        # releases without tar extraction filters. Never restore links or owners.
        if destination.is_symlink() or (destination.exists() and any(destination.iterdir())):
            raise ValueError("Release extraction requires a fresh empty directory.")
        destination.mkdir(parents=True, exist_ok=True)
        for member in bundle.getmembers():
            target = destination / member.name
            target.parent.mkdir(parents=True, exist_ok=True)
            with bundle.extractfile(member) as source, target.open("xb") as output:
                shutil.copyfileobj(source, output, length=1024 * 1024)
            target.chmod(0o755 if member.mode & 0o100 else 0o644)
    manifest = json.loads((destination / "manifest.json").read_text())
    expected_images = {f"holyspace-java-{component}:{commit}" for component in COMPONENTS}
    if (manifest["commit"] != commit or manifest["platform"] != "linux/amd64"
            or set(manifest["images"]) != expected_images):
        raise ValueError("Release commit, platform or images do not match the deployment request.")
    if digest(destination / "images.tar.gz") != manifest["image_archive_sha256"]:
        raise ValueError("Image archive checksum mismatch.")
    if set(manifest["files"]) != seen - {"manifest.json", "images.tar.gz"}:
        raise ValueError("Deployment file manifest does not match the archive.")
    for name, checksum in manifest["files"].items():
        if digest(destination / name) != checksum:
            raise ValueError("Deployment file checksum mismatch.")
    return manifest


def main():
    if len(sys.argv) != 2 or not re.fullmatch(r"[0-9a-f]{40}-[0-9]+-[0-9]+", sys.argv[1]):
        raise ValueError("Usage: receive-release.py COMMIT-RUN-ATTEMPT")
    release_id = sys.argv[1]
    commit = release_id.split("-")[0]
    root = Path("/srv/holyspace")
    # Fixed installation paths and a stable Compose project preserve the database volume.
    os.umask(0o077)
    with (root / "deploy.lock").open("a") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        archive = root / "incoming" / (release_id + ".tar")
        checksum_file = root / "incoming" / (release_id + ".tar.sha256")
        checksum = checksum_file.read_text().split()[0]
        if not re.fullmatch(r"[0-9a-f]{64}", checksum) or digest(archive) != checksum:
            raise ValueError("Uploaded release checksum mismatch.")
        destination = root / "releases" / release_id
        if destination.exists():
            raise ValueError("Release attempt already exists; retry with a new workflow attempt.")
        with tempfile.TemporaryDirectory(prefix=".incoming-", dir=root / "releases") as directory:
            staging = Path(directory)
            manifest = unpack(archive, staging, commit)
            # A release cannot bypass the architecture check with a tag in its manifest.
            architecture = subprocess.check_output(["docker", "info", "--format", "{{.Architecture}}"], text=True).strip()
            if architecture not in ("x86_64", "amd64"):
                raise ValueError("This pipeline targets an AMD64 VPS.")
            subprocess.run(["docker", "load", "--input", str(staging / "images.tar.gz")], check=True)
            for image, image_id in manifest["images"].items():
                actual = subprocess.check_output(["docker", "image", "inspect", "--format", "{{.Id}}", image], text=True).strip()
                if actual != image_id:
                    raise ValueError("Loaded image identity does not match the tested release.")
            staging.rename(destination)
        shared = root / "shared"
        lines = (shared / "production.env").read_text().splitlines()
        lines = [line for line in lines if not re.match(r"\s*RELEASE_TAG\s*=", line)]
        (destination / ".env.java-production.local").write_text("\n".join(lines) + f"\nRELEASE_TAG={commit}\n")
        recipient = (shared / "backup-recipient").read_text().strip()
        if not recipient or "\n" in recipient:
            raise ValueError("Configure a single age public backup recipient on the VPS.")
        env = {key: value for key, value in os.environ.items()
               if not key.startswith(("JAVA_PRODUCTION_", "COMPOSE_"))}
        env.update(JAVA_PRODUCTION_TARGET="nginx-api", JAVA_PRODUCTION_PREBUILT="true",
                   JAVA_PRODUCTION_PROJECT="holyspace-java-prod",
                   JAVA_PRODUCTION_ENV_FILE=str(destination / ".env.java-production.local"),
                   JAVA_PRODUCTION_SECURITY_REPORTS=str(destination / "security-reports"))
        subprocess.run(["bash", str(destination / "scripts/java-production/deploy.sh"),
                        recipient, str(root / "backups")], cwd=destination, env=env,
                       pass_fds=(lock.fileno(),), check=True)
        # Update the current release only after deployment and internal health/queue checks succeed.
        pending = root / "current.pending"
        pending.unlink(missing_ok=True)
        pending.symlink_to(destination, target_is_directory=True)
        pending.replace(root / "current")
        archive.unlink()
        checksum_file.unlink()
        print("Backend deployed successfully: " + commit)


if __name__ == "__main__":
    try:
        main()
    except (ValueError, KeyError, OSError, subprocess.CalledProcessError):
        # Do not include private environment configuration or credential values in CI logs.
        print("Deployment failed. Inspect the VPS release and service status; no schema rollback was attempted.", file=sys.stderr)
        sys.exit(1)
