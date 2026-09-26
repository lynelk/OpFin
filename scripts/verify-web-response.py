#!/usr/bin/env python3
"""Read-only HTTP smoke test. Never logs page bodies, cookies or nonce values."""
import re
import sys
import time
from html.parser import HTMLParser
from urllib.error import URLError
from urllib.parse import urlparse
from urllib.request import Request, urlopen

class Scripts(HTMLParser):
    def __init__(self):
        super().__init__()
        self.scripts = []
    def handle_starttag(self, tag, attrs):
        if tag == 'script':
            values = dict(attrs)
            if values.get('type', '').lower() not in ('application/json', 'application/ld+json'):
                self.scripts.append(values)

def fetch(base, path):
    with urlopen(Request(base + path, headers={'User-Agent': 'OpFin-release-smoke/1.0'}), timeout=15) as response:
        data = response.read(3_000_001)
        if len(data) > 3_000_000:
            raise ValueError('Unexpectedly large smoke-test response')
        return response.status, response.headers, data

def main():
    base = sys.argv[1].rstrip('/') if len(sys.argv) == 2 else ''
    target = urlparse(base)
    if target.scheme not in ('http', 'https') or not target.hostname or target.username or target.password or target.query or target.fragment:
        raise ValueError('Provide a plain HTTP(S) origin')
    if target.scheme == 'http' and target.hostname not in ('localhost', '127.0.0.1'):
        raise ValueError('Non-local smoke tests require HTTPS')
    for attempt in range(40):
        try:
            _, headers, body = fetch(base, '/')
            break
        except (URLError, TimeoutError, ConnectionError):
            if attempt == 39:
                raise
            time.sleep(1)
    policy = headers.get('Content-Security-Policy', '')
    match = re.search(r"'nonce-([A-Za-z0-9+/=_-]+)'", policy)
    assert match, 'Missing per-request CSP nonce'
    nonce = match.group(1)
    script_rule = next((item for item in policy.split(';') if item.strip().startswith('script-src ')), '')
    assert 'unsafe-eval' not in script_rule and 'unsafe-inline' not in script_rule, 'Unsafe production script policy'
    assert "frame-ancestors 'none'" in policy, 'Missing CSP anti-framing control'
    assert headers.get('X-Content-Type-Options') == 'nosniff', 'Missing MIME control'
    assert headers.get('X-Frame-Options') == 'DENY', 'Missing frame control'
    assert headers.get('Strict-Transport-Security'), 'Missing HSTS'
    assert 'no-store' in headers.get('Cache-Control', ''), 'Nonce HTML is cacheable'
    parser = Scripts()
    parser.feed(body.decode('utf8'))
    assert parser.scripts, 'No framework scripts were rendered'
    assert all(script.get('nonce') == nonce for script in parser.scripts), 'CSP nonce was not propagated to rendered scripts'
    assert b'Understand, manage, plan and improve your money.' in body, 'Missing current brand headline'
    assert b'/brand/opfin-symbol.svg' in body, 'Missing actual OpFin symbol'
    _, second_headers, _ = fetch(base, '/')
    assert second_headers.get('Content-Security-Policy') != policy, 'CSP nonce was reused'
    for path in ('/login', '/account/delete'):
        status, page_headers, _ = fetch(base, path)
        assert status == 200 and page_headers.get('Content-Security-Policy'), 'Public account page unavailable or unprotected'
    for path, content_type in (('/brand/opfin-symbol.png', 'image/png'), ('/brand/InterVariable.woff2', 'font/woff2')):
        status, asset_headers, content = fetch(base, path)
        assert status == 200 and content and content_type in asset_headers.get('Content-Type', ''), 'Brand asset missing or incorrectly served'
    print('Production HTTP checks passed: fresh CSP nonces, matching script nonces, security headers, public login/deletion pages and brand assets.')

if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('Web release smoke failed: ' + str(error), file=sys.stderr)
        sys.exit(1)
