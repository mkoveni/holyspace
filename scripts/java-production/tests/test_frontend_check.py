import importlib.util
import io
from email.message import Message
from pathlib import Path
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[3]
SPEC = importlib.util.spec_from_file_location("frontend_check", ROOT / "scripts/java-production/check-frontend.py")
frontend = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(frontend)
ORIGIN = "https://members.demo-church.org"


class FrontendCheckTests(unittest.TestCase):
    def check(self, asset_type="application/javascript", redirect=False, status="UP", missing_script=False):
        calls = []

        def fetch(url, **kwargs):
            calls.append(url)
            path = url.removeprefix(ORIGIN)
            content_type, content = {
                "/": ("text/html", b'<script type="module" src="/assets/app-123.js"></script>'),
                "/login": ("text/html", b'<script type="module" src="/assets/app-123.js"></script>'),
                "/assets/app-123.js": (asset_type, b"console.log('compiled');"),
                "/health/ready": ("application/json", ('{"status":"' + status + '"}').encode()),
            }[path]
            response = io.BytesIO(b"<html>fallback</html>" if missing_script else content)
            response.headers = Message()
            response.headers["Content-Type"] = content_type
            response.geturl = lambda: "https://other.invalid/" if redirect else url
            return response

        with patch.object(frontend, "urlopen", side_effect=fetch):
            frontend.check(ORIGIN)
        return calls

    def test_checks_home_login_javascript_and_api_relay(self):
        calls = self.check()
        self.assertIn(ORIGIN + "/login", calls)
        self.assertIn(ORIGIN + "/assets/app-123.js", calls)
        self.assertIn(ORIGIN + "/health/ready", calls)

    def test_rejects_spa_fallback_for_assets_redirects_missing_script_and_unhealthy_api(self):
        for options in ({"asset_type": "text/html"}, {"redirect": True},
                        {"missing_script": True}, {"status": "DOWN"}):
            with self.subTest(options=options), self.assertRaises(ValueError):
                self.check(**options)

    def test_requires_a_frontend_https_origin(self):
        self.assertEqual(frontend.validate_origin(ORIGIN + "/"), ORIGIN)
        for value in ("", "http://members.demo-church.org", ORIGIN + "/login",
                      "https://user:secret@members.demo-church.org", ORIGIN + "?test=1"):
            with self.subTest(value=value), self.assertRaises(ValueError):
                frontend.validate_origin(value)
