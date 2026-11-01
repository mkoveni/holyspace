import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[3]
COMMIT = "a" * 40


def module(name):
    spec = importlib.util.spec_from_file_location(name, ROOT / "scripts/java-production" / (name + ".py"))
    loaded = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(loaded)
    return loaded


receiver = module("receive-release")
packager = module("package-release")


class ReleaseArchiveTests(unittest.TestCase):
    def test_packaging_allowlist_excludes_credentials_and_source(self):
        names = {str(path.relative_to(ROOT)) for path in packager.release_files()}
        self.assertIn("compose.java-production.yaml", names)
        self.assertIn("scripts/java-production/deploy.sh", names)
        for name in names:
            self.assertNotIn(".env", name)
            self.assertFalse(name.startswith(("backend-java/", "frontend/", "mobile/")))
            self.assertNotIn(name, ("yoco.http", "yoco_webhook.json"))

    def archive(self, path, extra=None, corrupt=False):
        payload = b"test-image-archive"
        config = b"services: {}\n"
        manifest = {
            "commit": COMMIT, "platform": "linux/amd64",
            "images": {f"holyspace-java-{component}:{COMMIT}": "sha256:" + "b" * 64
                       for component in receiver.COMPONENTS},
            "image_archive_sha256": hashlib.sha256(payload).hexdigest(),
            "files": {"compose.java-production.yaml": hashlib.sha256(config).hexdigest()},
        }
        entries = {"manifest.json": json.dumps(manifest).encode(), "images.tar.gz": payload,
                   "compose.java-production.yaml": b"tampered" if corrupt else config}
        with tarfile.open(path, "w") as archive:
            for name, content in entries.items():
                info = tarfile.TarInfo(name)
                info.size = len(content)
                archive.addfile(info, io.BytesIO(content))
            if extra:
                archive.addfile(extra)

    def test_release_commit_and_checksums_are_verified(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            self.archive(path / "release.tar")
            self.assertEqual(receiver.unpack(path / "release.tar", path / "valid", COMMIT)["commit"], COMMIT)
            with self.assertRaises(ValueError):
                receiver.unpack(path / "release.tar", path / "wrong-commit", "c" * 40)
            self.archive(path / "release.tar", corrupt=True)
            with self.assertRaises(ValueError):
                receiver.unpack(path / "release.tar", path / "tampered", COMMIT)

    def test_release_rejects_traversal_links_and_duplicate_entries(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            for name, kind in (("../outside", tarfile.REGTYPE),
                               ("scripts/java-production/link", tarfile.SYMTYPE),
                               ("images.tar.gz", tarfile.REGTYPE)):
                with self.subTest(name=name):
                    entry = tarfile.TarInfo(name)
                    entry.type = kind
                    entry.linkname = "/etc/passwd" if kind == tarfile.SYMTYPE else ""
                    self.archive(path / "release.tar", extra=entry)
                    with self.assertRaises(ValueError):
                        receiver.unpack(path / "release.tar", path / "unsafe", COMMIT)
            self.assertFalse((path / "outside").exists())

    def test_release_cannot_extract_through_existing_files_or_links(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            self.archive(path / "release.tar")
            destination = path / "existing"
            destination.mkdir()
            original = destination / "manifest.json"
            original.write_text("preserve")
            with self.assertRaises(ValueError):
                receiver.unpack(path / "release.tar", destination, COMMIT)
            self.assertEqual(original.read_text(), "preserve")
            linked = path / "linked"
            linked.symlink_to(destination, target_is_directory=True)
            with self.assertRaises(ValueError):
                receiver.unpack(path / "release.tar", linked, COMMIT)

    def test_receiver_hashes_streamed_data_on_python310(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "image"
            content = b"release-image" * 100000
            path.write_bytes(content)
            self.assertEqual(receiver.digest(path), hashlib.sha256(content).hexdigest())
            self.assertEqual(packager.digest(path), hashlib.sha256(content).hexdigest())


class PrebuiltDeploymentTests(unittest.TestCase):
    def deploy(self, directory, fail_at=""):
        root = Path(directory)
        scripts = root / "scripts/java-production"
        scripts.mkdir(parents=True)
        (root / "docker/java-production").mkdir(parents=True)
        (root / "docker/java-production/grants.sql").touch()
        for name in ("deploy.sh", "common.sh"):
            (scripts / name).write_bytes((ROOT / "scripts/java-production" / name).read_bytes())
        (scripts / "preflight.py").write_text("print('Fixture preflight passed')\n")
        for name in ("backup", "security-scan", "check"):
            script = scripts / (name + ".sh")
            script.write_text(f'#!/bin/bash\necho {name} >> "$TEST_LOG"\n'
                              f'[[ "${{FAIL_AT:-}}" != "{name}" ]]\n')
            script.chmod(0o700)
        binary = root / "bin"
        binary.mkdir()
        docker = binary / "docker"
        config = {"services": {component: {"image": f"holyspace-java-{component}:{COMMIT}"}
                               for component in receiver.COMPONENTS},
                  "volumes": {"database": {"name": "holyspace-java-prod_database"}}}
        docker.write_text('#!/bin/bash\necho "docker $*" >> "$TEST_LOG"\n'
                          'if [[ " $* " == *" image inspect "* && "${FAIL_AT:-}" == image-inspect ]]; then exit 23; fi\n'
                          'if [[ " $* " == *" postgres --version "* && "${FAIL_AT:-}" == runtime-check ]]; then exit 1; fi\n'
                          'if [[ " $* " == *" config "* ]]; then\n'
                          "cat <<'JSON'\n" + json.dumps(config) + '\nJSON\nfi\n')
        docker.chmod(0o700)
        env = {key: value for key, value in os.environ.items() if not key.startswith("JAVA_PRODUCTION_")}
        env.update(PATH=str(binary) + os.pathsep + os.environ["PATH"], TEST_LOG=str(root / "calls"),
                   JAVA_PRODUCTION_TARGET="nginx-api", JAVA_PRODUCTION_PREBUILT="true", FAIL_AT=fail_at)
        result = subprocess.run(["bash", str(scripts / "deploy.sh"), "age1test", str(root / "backups")],
                                env=env, capture_output=True, text=True)
        return result, (root / "calls").read_text()

    def test_same_images_are_used_with_backup_before_maintenance_and_migration(self):
        with tempfile.TemporaryDirectory() as directory:
            result, calls = self.deploy(directory)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertNotIn(" build ", calls)
            self.assertIn("--no-build --pull never", calls)
            self.assertLess(calls.index("security-scan\n"), calls.index("backup\n"))
            self.assertLess(calls.index("backup\n"), calls.index(" stop api"))
            self.assertLess(calls.index(" stop api"), calls.index("run --rm -T --no-deps migrate"))
            self.assertIn("Deployment: completed successfully", result.stdout)

    def test_silent_image_failure_reports_stage_and_preserves_exit_status(self):
        with tempfile.TemporaryDirectory() as directory:
            result, calls = self.deploy(directory, fail_at="image-inspect")
            self.assertEqual(result.returncode, 23)
            self.assertIn("Deployment: verifying loaded api image", result.stdout)
            self.assertIn("Deployment failed during verifying loaded api image (exit code 23).", result.stderr)
            self.assertNotIn("completed successfully", result.stdout)
            self.assertNotIn(" stop api", calls)
            self.assertNotIn("security-scan\n", calls)

    def test_security_or_backup_failure_cannot_stop_the_running_api(self):
        for failure in ("security-scan", "backup", "runtime-check"):
            with self.subTest(failure=failure), tempfile.TemporaryDirectory() as directory:
                result, calls = self.deploy(directory, fail_at=failure)
                self.assertNotEqual(result.returncode, 0)
                self.assertIn("Deployment failed during", result.stderr)
                self.assertNotIn(" stop api", calls)
                self.assertNotIn("run --rm -T --no-deps migrate", calls)
                if failure == "runtime-check":
                    self.assertNotIn(" up -d ", calls)


class SshTransferTests(unittest.TestCase):
    def test_transfer_pins_host_key_and_sends_only_the_validated_release_id(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            binary = root / "bin"
            binary.mkdir()
            for name in ("ssh", "scp"):
                script = binary / name
                script.write_text('#!/bin/bash\necho "$0 $*" >> "$TEST_LOG"\n')
                script.chmod(0o700)
            content = b"fixture-release"
            (root / "holyspace-release.tar").write_bytes(content)
            (root / "holyspace-release.tar.sha256").write_text(
                hashlib.sha256(content).hexdigest() + "  holyspace-release.tar\n")
            env = {**os.environ, "PATH": str(binary) + os.pathsep + os.environ["PATH"],
                   "TEST_LOG": str(root / "calls"), "VPS_HOST": "deploy.verification.invalid",
                   "VPS_USER": "holyspace-deploy", "VPS_SSH_PORT": "2222",
                   "VPS_SSH_KEY": "fixture-key", "VPS_KNOWN_HOSTS": "fixture-host-key",
                   "GITHUB_SHA": COMMIT, "GITHUB_RUN_ID": "123", "GITHUB_RUN_ATTEMPT": "2"}
            script = ROOT / "scripts/java-production/ci-deploy.sh"
            result = subprocess.run(["bash", str(script), str(root)], env=env, capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            calls = (root / "calls").read_text().splitlines()
            self.assertEqual(len(calls), 3)
            for call in calls:
                self.assertIn("StrictHostKeyChecking=yes", call)
                self.assertIn("UserKnownHostsFile=", call)
                self.assertIn("IdentitiesOnly=yes", call)
                self.assertNotIn("fixture-key", call)
            self.assertIn(f"receive-release.py {COMMIT}-123-2", calls[-1])
            self.assertIn("/usr/bin/python3 -u ", calls[-1])
            self.assertIn("VPS release completed successfully", result.stdout)
            (root / "calls").unlink()
            env["VPS_HOST"] = "host;exit 0"
            self.assertNotEqual(subprocess.run(["bash", str(script), str(root)], env=env,
                                              capture_output=True).returncode, 0)
            self.assertFalse((root / "calls").exists())


if __name__ == "__main__":
    unittest.main()
