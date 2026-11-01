import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[3]


class BootstrapMemoryTests(unittest.TestCase):
    def bootstrap(self, running=True, failed=False, removal_failed=False, unhealthy=False):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            scripts = root / "scripts/java-production"
            scripts.mkdir(parents=True)
            for name in ("bootstrap.sh", "common.sh"):
                shutil.copyfile(ROOT / "scripts/java-production" / name, scripts / name)
            password = root / "password"
            password.write_text("fixture-only-password")
            binary = root / "bin"
            binary.mkdir()
            docker = binary / "docker"
            docker.write_text('''#!/bin/bash
echo "$*" >> "$TEST_LOG"
case " $* " in
  *" ps --status running -q api "*) [[ "$RUNNING" != true ]] || echo existing-api ;;
  *" run -d "*) echo bootstrap-container ;;
  *" logs bootstrap-container "*)
    if [[ "$FAILED" == true ]]; then
      echo 'Administrator bootstrap is only allowed before the first account exists.'
    else
      echo 'First administrator created. Disable bootstrap before starting the API.'
    fi ;;
  *" inspect "*" existing-api "*)
    if [[ "$UNHEALTHY" == true ]]; then echo 'exited unhealthy'; else echo 'running healthy'; fi ;;
  *" inspect "*) echo false ;;
  *" rm -f bootstrap-container "*) [[ "$REMOVAL_FAILED" != true ]] ;;
esac
''')
            docker.chmod(0o700)
            env = {key: value for key, value in os.environ.items() if not key.startswith("JAVA_PRODUCTION_")}
            env.update(PATH=str(binary) + os.pathsep + os.environ["PATH"], TEST_LOG=str(root / "calls"),
                       JAVA_PRODUCTION_TARGET="nginx-app", RUNNING=str(running).lower(),
                       FAILED=str(failed).lower(), REMOVAL_FAILED=str(removal_failed).lower(),
                       UNHEALTHY=str(unhealthy).lower())
            result = subprocess.run(["bash", str(scripts / "bootstrap.sh"), "admin", "admin@demo.org",
                                     "Administrator", str(password)], env=env, capture_output=True, text=True)
            return result, (root / "calls").read_text()

    def test_stops_running_api_and_removes_temporary_jvm_before_restarting(self):
        result, calls = self.bootstrap()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertLess(calls.index("stop existing-api"), calls.index(" run -d"))
        self.assertLess(calls.index("rm -f bootstrap-container"), calls.index("start existing-api"))
        self.assertIn("{{.State.Status}}", calls)
        self.assertIn("-Xmx256m", calls)
        self.assertNotIn("fixture-only-password", calls + result.stdout + result.stderr)

    def test_existing_account_failure_restores_previously_running_api(self):
        result, calls = self.bootstrap(failed=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("start existing-api", calls)
        self.assertIn("Administrator bootstrap refused: an account already exists.", result.stderr)

    def test_initial_bootstrap_does_not_start_an_api_that_was_stopped(self):
        result, calls = self.bootstrap(running=False)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertNotIn("stop existing-api", calls)
        self.assertNotIn("start existing-api", calls)

    def test_failed_temporary_container_removal_never_starts_a_second_jvm(self):
        result, calls = self.bootstrap(removal_failed=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn("start existing-api", calls)

    def test_api_exit_after_restart_cannot_report_bootstrap_success(self):
        result, calls = self.bootstrap(unhealthy=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("The regular API could not restart.", result.stderr)
        self.assertIn("OOMKilled", calls)
