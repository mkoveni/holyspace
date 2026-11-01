import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[3]


class SecurityScanTests(unittest.TestCase):
    def scan(self, oom=False):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            binary = root / "bin"
            binary.mkdir()
            docker = binary / "docker"
            docker.write_text('''#!/bin/bash
echo "$*" >> "$TEST_LOG"
case "$1" in
  run)
    for arg in "$@"; do
      case "$arg" in
        *:/reports) report_directory=${arg%:/reports} ;;
        /reports/*.json) report_file=${arg#/reports/} ;;
      esac
    done
    if [[ "$report_file" == database.json && "$FAIL_DATABASE" == true ]]; then exit 137; fi
    printf '{"Results": []}' > "$report_directory/$report_file" ;;
  inspect)
    if [[ "$*" == *holyspace-security-database* && "$FAIL_DATABASE" == true ]]; then
      echo true
    else
      echo false
    fi ;;
esac
''')
            docker.chmod(0o700)
            env = {**os.environ, "PATH": str(binary) + os.pathsep + os.environ["PATH"],
                   "JAVA_PRODUCTION_TARGET": "nginx-app", "SCANNER_MEMORY_LIMIT": "1g",
                   "SCANNER_GO_MEMORY_LIMIT": "768MiB", "FAIL_DATABASE": str(oom).lower(),
                   "TEST_LOG": str(root / "calls")}
            env.pop("GITHUB_STEP_SUMMARY", None)
            result = subprocess.run(["bash", str(ROOT / "scripts/java-production/security-scan.sh"),
                                     "fixture", str(root / "reports")], env=env, capture_output=True, text=True)
            return result, (root / "calls").read_text()

    def test_ci_budget_is_applied_and_every_scanner_container_is_removed(self):
        result, calls = self.scan()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(calls.count("--memory 1g --memory-swap 1g"), 4)
        self.assertEqual(calls.count("GOMEMLIMIT=768MiB"), 4)
        self.assertEqual(calls.count("rm -f holyspace-security-"), 4)

    def test_database_oom_is_reported_and_cannot_pass_the_security_gate(self):
        result, calls = self.scan(oom=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("component=database exit=137 OOMKilled=true", result.stdout)
        self.assertIn("OOM-killed", result.stdout)
        self.assertIn("Image security gate failed", result.stderr)
        self.assertEqual(calls.count("rm -f holyspace-security-"), 4)
