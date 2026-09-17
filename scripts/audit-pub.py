#!/usr/bin/env python3
"""Query public Pub package versions only; never transmit source, tokens or user data."""
import json
import re
import sys
import time
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[1]

def read_packages(text):
    if not text.startswith('#') and not text.startswith('packages:'):
        raise ValueError('Unexpected lockfile structure')
    if '\npackages:\n' not in '\n' + text or '\nsdks:' not in text:
        raise ValueError('Missing Pub lockfile sections')
    body = text.split('packages:\n', 1)[1].split('\nsdks:', 1)[0]
    blocks = re.findall(r'^  ([a-zA-Z0-9_]+):\n(.*?)(?=^  [a-zA-Z0-9_]+:\n|\Z)', body, re.M | re.S)
    if not blocks:
        raise ValueError('No packages found: cannot certify an empty scan')
    packages, excluded = [], []
    for name, block in blocks:
        source = re.search(r'^    source: ([a-z]+)\s*$', block, re.M)
        version = re.search(r'^    version: [\"\']?([^\"\'\s]+)', block, re.M)
        if not source or not version:
            raise ValueError('Incomplete locked package: ' + name)
        if source.group(1) == 'sdk':
            excluded.append(name)
            continue
        if source.group(1) != 'hosted' or not re.search(r'url: [\"\']?https://pub\.(dev|dartlang\.org)[/\"\'\s]', block + '\n'):
            raise ValueError('Non-public or unsupported package requires explicit security review: ' + name)
        packages.append({'package': {'name': name, 'ecosystem': 'Pub'}, 'version': version.group(1)})
    if not packages:
        raise ValueError('No hosted packages were covered')
    return packages, excluded

def request_batch(queries):
    payload = json.dumps({'queries': queries}).encode()
    for attempt in range(3):
        try:
            request = Request('https://api.osv.dev/v1/querybatch', data=payload, headers={'Content-Type': 'application/json', 'User-Agent': 'OpFin-dependency-audit/1.0'}, method='POST')
            with urlopen(request, timeout=40) as response:
                result = json.load(response)
            if not isinstance(result.get('results'), list) or len(result['results']) != len(queries):
                raise ValueError('Incomplete vulnerability response')
            return result['results']
        except (HTTPError, URLError, TimeoutError):
            if attempt == 2:
                raise
            time.sleep(2 ** attempt)

def main():
    packages, excluded = read_packages((ROOT / 'apps/client/pubspec.lock').read_text())
    findings = set()
    for start in range(0, len(packages), 100):
        pending = packages[start:start + 100]
        page_count = 0
        while pending:
            page_count += 1
            if page_count > 100:
                raise ValueError('Pagination exceeded safety limit; scan incomplete')
            results = request_batch(pending)
            followups = []
            for query, result in zip(pending, results):
                if not isinstance(result, dict) or result.get('error'):
                    raise ValueError('Package vulnerability query failed')
                for finding in result.get('vulns', []):
                    identifier = finding.get('id')
                    if not isinstance(identifier, str) or not re.fullmatch(r'[A-Za-z0-9._-]+', identifier):
                        raise ValueError('Invalid advisory identifier')
                    findings.add((query['package']['name'], query['version'], identifier))
                if result.get('next_page_token'):
                    followups.append({**query, 'page_token': result['next_page_token']})
            pending = followups
    print(f'OSV queried {len(packages)} locked public Pub dependencies. SDK packages are not covered by this package scan: {", ".join(excluded)}')
    for name, version, identifier in sorted(findings):
        print(f'Advisory: {name}@{version}: {identifier}')
    if findings:
        print('Security review required. Advisory findings are not silently ignored.', file=sys.stderr)
        return 1
    print('No published advisory matches were returned for the covered Pub versions at scan time.')
    return 0

if __name__ == '__main__':
    try:
        sys.exit(main())
    except Exception as error:
        print('Pub security scan incomplete: ' + type(error).__name__, file=sys.stderr)
        sys.exit(2)
