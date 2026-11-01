import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

SPEC = importlib.util.spec_from_file_location(
    "security_summary", Path(__file__).resolve().parents[1] / "security-summary.py")
summary = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(summary)


class SecuritySummaryTests(unittest.TestCase):
    def test_missing_report_with_confirmed_oom_explains_memory_failure_and_still_blocks(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            (path / "database.status.json").write_text('{"oom_killed": true, "exit_code": 137}')
            text, blocked = summary.summarize(path, [("database", 137)])
            self.assertTrue(blocked)
            self.assertIn("Scanner exit code: `137`", text)
            self.assertIn("OOM-killed", text)
            self.assertNotIn("Passed:", text)

    def test_unfixed_findings_still_block_and_show_the_package_and_advisory(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            (path / "database.json").write_text(json.dumps({"Results": [{"Vulnerabilities": [{
                "Severity": "HIGH", "VulnerabilityID": "CVE-2026-example", "PkgName": "stdlib",
                "InstalledVersion": "old", "FixedVersion": ""}]}]}))
            text, blocked = summary.summarize(path, [("database", 1)])
            self.assertTrue(blocked)
            self.assertIn("No vendor fix listed", text)
            self.assertIn("CVE-2026-example", text)
            self.assertIn("stdlib", text)

    def test_missing_or_invalid_report_and_scanner_errors_cannot_pass(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            self.assertTrue(summary.summarize(path, [("api", 1)])[1])
            (path / "api.json").write_text("not-json")
            self.assertTrue(summary.summarize(path, [("api", 0)])[1])
            (path / "api.json").write_text('{"Results": []}')
            self.assertTrue(summary.summarize(path, [("api", 1)])[1])
            self.assertFalse(summary.summarize(path, [("api", 0)])[1])


if __name__ == "__main__":
    unittest.main()
