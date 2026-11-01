#!/usr/bin/env python3
"""Check the public Java frontend, its compiled JavaScript and API relay."""
from html.parser import HTMLParser
import json
import os
import sys
from urllib.parse import urlsplit
from urllib.request import urlopen


class Scripts(HTMLParser):
    def __init__(self):
        super().__init__()
        self.sources = []

    def handle_starttag(self, tag, attrs):
        if tag == "script":
            self.sources += [value for name, value in attrs if name == "src" and value]


def validate_origin(value):
    parsed = urlsplit(value)
    if (parsed.scheme != "https" or not parsed.hostname or parsed.username or parsed.password
            or parsed.path not in ("", "/") or parsed.query or parsed.fragment or parsed.port not in (None, 443)):
        raise ValueError("PRODUCTION_FRONTEND_URL must be an HTTPS origin.")
    return value.rstrip("/")


def check(base):
    def fetch(path):
        with urlopen(base + path, timeout=20) as response:
            # Do not accept redirects to another origin, or a fallback login page for an asset.
            if response.geturl() != base + path:
                raise ValueError("Frontend check received an unexpected redirect.")
            content = response.read(2 * 1024 * 1024 + 1)
            if len(content) > 2 * 1024 * 1024:
                raise ValueError("Frontend response exceeded the check size limit.")
            return response.headers.get_content_type(), content

    for route in ("/", "/login"):
        content_type, html = fetch(route)
        if content_type != "text/html":
            raise ValueError("Frontend did not serve HTML.")
        scripts = Scripts()
        scripts.feed(html.decode("utf-8"))
        assets = [source for source in scripts.sources
                  if source.startswith("/assets/") and source.endswith(".js")
                  and not urlsplit(source).query and ".." not in source]
        if not assets:
            raise ValueError("Frontend HTML is missing its compiled JavaScript entry point.")
        for source in assets:
            asset_type, content = fetch(source)
            if asset_type not in ("application/javascript", "text/javascript") or not content:
                raise ValueError("Frontend JavaScript asset is missing or returned HTML.")
    _, readiness = fetch("/health/ready")
    if json.loads(readiness).get("status") != "UP":
        raise ValueError("Frontend API relay is not ready.")


def main():
    try:
        check(validate_origin(os.environ.get("PRODUCTION_FRONTEND_URL", "")))
    except (OSError, ValueError):
        # Never echo response bodies, payment capabilities or URLs from redirects.
        print("Public frontend check failed: verify HTTPS, SPA routes, JavaScript assets and API readiness.", file=sys.stderr)
        return 1
    print("Public Java frontend, JavaScript assets and API relay checks passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
