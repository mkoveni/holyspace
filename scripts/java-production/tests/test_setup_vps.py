from dataclasses import replace
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
import zipfile

ROOT = Path(__file__).resolve().parents[3]
SPEC = importlib.util.spec_from_file_location("setup_vps", ROOT / "scripts/java-production/setup-vps.py")
setup = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = setup
SPEC.loader.exec_module(setup)
SETTINGS = setup.Settings("api.demo-church.org", "members.demo-church.org", "office@demo-church.org",
                          "owner/holyspace", "vps.demo-church.org", 22)


class SetupInputTests(unittest.TestCase):
    def test_ubuntu22_accepts_standard_python310_without_an_interpreter_upgrade(self):
        setup.validate_platform({"ID": "ubuntu", "VERSION_ID": '"22.04"'}, (3, 10), "x86_64")
        with self.assertRaises(ValueError):
            setup.validate_platform({"ID": "ubuntu", "VERSION_ID": '"22.04"'}, (3, 9), "x86_64")

    def test_invalid_domains_and_configuration_cannot_be_interpolated_into_host_files(self):
        self.assertEqual(SETTINGS.validate(), SETTINGS)
        for changes in ({"api_domain": "api.site.org; include /etc/passwd"},
                        {"api_domain": SETTINGS.frontend_domain}, {"ssh_host": "host;id"},
                        {"ssh_port": 0}, {"repository": "owner/repo/extra"},
                        {"backup_recipient": "not-an-age-key"}):
            with self.subTest(changes=changes), self.assertRaises(ValueError):
                replace(SETTINGS, **changes).validate()

    def test_standalone_bundle_has_exact_assets_and_plan_never_changes_the_host(self):
        with tempfile.TemporaryDirectory() as directory:
            bundle = Path(directory) / "installer.pyz"
            setup.bundle_setup(bundle)
            with zipfile.ZipFile(bundle) as archive:
                self.assertEqual(set(archive.namelist()), {"__main__.py", *setup.ASSETS})
                for name in setup.ASSETS:
                    self.assertEqual(archive.read(name), (ROOT / name).read_bytes())
            result = subprocess.run([sys.executable, str(bundle), "--plan", "--api-domain", SETTINGS.api_domain,
                                     "--frontend-domain", SETTINGS.frontend_domain, "--email", SETTINGS.email,
                                     "--repository", SETTINGS.repository, "--ssh-host", SETTINGS.ssh_host],
                                    capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertIn("Plan:", result.stdout)
            self.assertNotIn("PRIVATE KEY", result.stdout)


class SetupSecretTests(unittest.TestCase):
    def test_binary_package_keyring_is_written_without_text_conversion(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "keyring.gpg"
            content = b"\x99\x01\xff\x00binary-keyring"
            setup.protected_write(path, content, mode=0o644, uid=os.getuid(), gid=os.getgid())
            self.assertEqual(path.read_bytes(), content)

    def test_reruns_preserve_all_secrets_and_live_installs_cannot_regenerate_missing_keys(self):
        def write(path, content, **kwargs):
            return original(path, content, **{**kwargs, "uid": os.getuid(), "gid": os.getgid()})

        original = setup.protected_write
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            with patch.object(setup, "protected_write", side_effect=write):
                setup.create_secrets(path)
                original_contents = {file.name: file.read_bytes() for file in path.iterdir()}
                setup.create_secrets(path, existing_installation=True)
                self.assertEqual({file.name: file.read_bytes() for file in path.iterdir()}, original_contents)
                (path / "payments_key").unlink()
                with self.assertRaises(ValueError):
                    setup.create_secrets(path, existing_installation=True)
                self.assertFalse((path / "payments_key").exists())

    def test_symlink_cannot_redirect_a_privileged_write(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            target = path / "original"
            target.write_text("preserve")
            link = path / "link"
            link.symlink_to(target)
            with self.assertRaises(ValueError):
                setup.protected_write(link, "changed")
            self.assertEqual(target.read_text(), "preserve")


class SetupNginxTests(unittest.TestCase):
    def test_http_stage_does_not_reference_unissued_certificate(self):
        config = setup.nginx_config(SETTINGS.api_domain, tls=False)
        self.assertIn("/.well-known/acme-challenge/", config)
        self.assertNotIn("ssl_certificate", config)
        full = setup.nginx_config(SETTINGS.api_domain)
        self.assertIn("127.0.0.1:18082", full)
        self.assertIn("/live/" + SETTINGS.api_domain + "/fullchain.pem", full)
        self.assertNotIn("api.church.example.org", full)

    def test_frontend_site_uses_its_own_domain_certificate_and_loopback_port(self):
        config = setup.nginx_config(SETTINGS.frontend_domain, frontend=True)
        self.assertIn("127.0.0.1:18080", config)
        self.assertIn("server_name " + SETTINGS.frontend_domain, config)
        self.assertIn("/live/" + SETTINGS.frontend_domain + "/fullchain.pem", config)
        self.assertNotIn("church.example.org", config)
        self.assertIn('proxy_set_header X-Forwarded-Proto $scheme;', config)
        http = setup.nginx_config(SETTINGS.frontend_domain, tls=False, frontend=True)
        self.assertIn("/.well-known/acme-challenge/", http)
        self.assertNotIn("ssl_certificate", http)

    def test_manually_edited_nginx_site_is_preserved(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            site = path / "site"
            site.write_text("server { # custom configuration\n}\n")
            with patch.object(setup, "SITE", site), patch.object(setup, "PRIVATE", path):
                with self.assertRaises(ValueError):
                    setup.install_site(SETTINGS)
            self.assertEqual(site.read_text(), "server { # custom configuration\n}\n")

    def test_manually_edited_frontend_site_is_preserved(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            site = path / "frontend-site"
            site.write_text("server { # existing frontend\n}\n")
            with patch.object(setup, "FRONTEND_SITE", site), patch.object(setup, "PRIVATE", path):
                with self.assertRaises(ValueError):
                    setup.install_site(SETTINGS, frontend=True)
            self.assertEqual(site.read_text(), "server { # existing frontend\n}\n")


class SetupGithubTests(unittest.TestCase):
    def configure(self, directory, fail_secret=False, new_environment=False, browser_login=False):
        commands = []
        api_commands = []

        def api(args, **kwargs):
            api_commands.append((args, kwargs))
            method, path = args[3:5]
            if new_environment and method == "GET" and path.endswith("/environments/production"):
                return subprocess.CompletedProcess(args, 1, "", "HTTP 404")
            payload = ({"permissions": {"admin": True}, "default_branch": "main"}
                       if path == "repos/owner/holyspace"
                       else {"protection_rules": [{"type": "required_reviewers"}]})
            return subprocess.CompletedProcess(args, 0, json.dumps(payload), "")

        def command(*args, **kwargs):
            commands.append((args, kwargs))
            if fail_secret and args[1:3] == ("secret", "set"):
                raise subprocess.CalledProcessError(1, "gh")

        with patch.object(setup, "PRIVATE", Path(directory)), patch.object(setup, "run", side_effect=command), \
                patch.object(setup.subprocess, "run", side_effect=api), \
                patch.dict(os.environ, {"GH_TOKEN": "" if browser_login else "fixture-token", "GITHUB_TOKEN": ""}):
            if fail_secret:
                with self.assertRaises(subprocess.CalledProcessError):
                    setup.configure_github(SETTINGS, {"VPS_SSH_KEY": "fixture-private-key"})
            else:
                setup.configure_github(SETTINGS, {"VPS_SSH_KEY": "fixture-private-key"})
        return commands, api_commands

    def test_existing_environment_rules_survive_and_secret_is_only_passed_on_stdin(self):
        with tempfile.TemporaryDirectory() as directory:
            commands, api = self.configure(directory)
            self.assertFalse(any("PUT" in args or "POST" in args for args, _ in api))
            secret = next((args, kwargs) for args, kwargs in commands if args[1:3] == ("secret", "set"))
            self.assertNotIn("fixture-private-key", secret[0])
            self.assertEqual(secret[1]["input"], "fixture-private-key")
            self.assertIn("PRODUCTION_DEPLOY_ENABLED", commands[-1][0])
            self.assertTrue(any("PRODUCTION_FRONTEND_URL" in args and "https://" + SETTINGS.frontend_domain in args
                                for args, _ in commands))
            self.assertFalse(Path(secret[1]["env"]["GH_CONFIG_DIR"]).exists())

    def test_new_environment_is_main_only_and_browser_credentials_are_temporary(self):
        with tempfile.TemporaryDirectory() as directory:
            commands, api = self.configure(directory, new_environment=True, browser_login=True)
            creation = next(kwargs for args, kwargs in api if "PUT" in args)
            self.assertEqual(json.loads(creation["input"])["deployment_branch_policy"],
                             {"protected_branches": False, "custom_branch_policies": True})
            policy = next(kwargs for args, kwargs in api if "POST" in args)
            self.assertEqual(json.loads(policy["input"]), {"name": "main", "type": "branch"})
            login = next((args, kwargs) for args, kwargs in commands if args[1:3] == ("auth", "login"))
            self.assertIn("--insecure-storage", login[0])
            self.assertFalse(Path(login[1]["env"]["GH_CONFIG_DIR"]).exists())

    def test_secret_upload_failure_cannot_enable_deployment(self):
        with tempfile.TemporaryDirectory() as directory:
            commands, _ = self.configure(directory, fail_secret=True)
            self.assertFalse(any("PRODUCTION_DEPLOY_ENABLED" in args for args, _ in commands))


if __name__ == "__main__":
    unittest.main()
