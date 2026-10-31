#!/usr/bin/env python3
"""HTTP acceptance against disposable release data. Never writes tokens to disk/logs."""
import json
import os
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

base = os.environ['JAVA_VERIFY_BASE_URL']


def request(path, method='GET', body=None, headers=None):
    data = None if body is None else json.dumps(body).encode()
    values = {'Content-Type': 'application/json', **(headers or {})}
    try:
        with urlopen(Request(base + path, data=data, headers=values, method=method), timeout=30) as response:
            return response.status, response.headers, response.read()
    except HTTPError as response:
        return response.code, response.headers, response.read()


status, headers, body = request('/people')
assert status == 200 and b'<div id="app">' in body
assert "frame-ancestors 'none'" in headers['Content-Security-Policy']
assert headers['Cache-Control'] == 'no-store'
assert request('/health/ready')[0] == 200
assert request('/api/me')[0] == 401
status, headers, _ = request('/api/me', 'OPTIONS', headers={
    'Origin': 'https://verify.church.invalid', 'Access-Control-Request-Method': 'GET',
    'Access-Control-Request-Headers': 'authorization'})
assert status == 200 and headers['Access-Control-Allow-Origin'] == 'https://verify.church.invalid'
assert request('/api/me', 'OPTIONS', headers={
    'Origin': 'https://foreign.invalid', 'Access-Control-Request-Method': 'GET'})[0] == 403
assert request('/api/me', 'OPTIONS', headers={
    'Origin': 'https://foreign.invalid', 'Access-Control-Request-Method': 'GET',
    'Forwarded': 'host=foreign.invalid;proto=https',
    'X-Forwarded-Host': 'foreign.invalid', 'X-Forwarded-Proto': 'https',
    'X-Forwarded-Port': '443'})[0] == 403
status, _, body = request('/api/login_check', 'POST', {
    'username': 'release-verification',
    'password': Path(os.environ['JAVA_VERIFY_PASSWORD_FILE']).read_text().strip(),
    'deviceName': 'Disposable release rehearsal'})
assert status == 200, 'Fixture login failed'
token = json.loads(body)['token']
auth = {'Authorization': 'Bearer ' + token}
status, _, body = request('/api/me', headers=auth)
assert status == 200 and json.loads(body)['username'] == 'release-verification'
status, _, body = request('/api/member/push', headers=auth)
assert status == 200 and json.loads(body)['available'] is False
assert request('/api/logout', 'POST', headers=auth)[0] == 204
assert request('/api/me', headers=auth)[0] == 401
print('Production relay, security headers, CORS and live-session smoke checks passed.')
