#!/usr/bin/env python3
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / 'distribution' / 'sponsored-data' / 'whitelist-manifest.json'
CLIENT = ROOT / 'apps' / 'client' / 'lib'

def fail(message: str, errors: list[str]) -> None:
    errors.append(message)

def main() -> int:
    errors: list[str] = []
    data = json.loads(MANIFEST.read_text(encoding='utf-8'))
    hosts = {row['host'].lower() for row in data.get('client_whitelist_hosts', []) if row.get('host')}
    if not hosts:
        fail('whitelist manifest has no client_whitelist_hosts', errors)
    if data.get('status') not in {'candidate', 'approved'}:
        fail('whitelist manifest status must be candidate or approved', errors)
    approval = data.get('approval') or {}
    if data.get('status') == 'approved' and not approval.get('operator_reference'):
        fail('approved sponsorship requires an operator_reference', errors)

    constants = (CLIENT / 'constants.dart').read_text(encoding='utf-8')
    match = re.search(r"defaultValue:\s*'https://([^/']+)", constants)
    if not match:
        fail('cannot determine Flutter default API host', errors)
    elif match.group(1).lower() not in hosts:
        fail(f"Flutter default API host {match.group(1)} is absent from whitelist manifest", errors)

    direct_pattern = re.compile(r'\bhttp\.(get|post|put|patch|delete|head)\s*\(')
    url_pattern = re.compile(r'https://([A-Za-z0-9.-]+)')
    for path in CLIENT.rglob('*.dart'):
        text = path.read_text(encoding='utf-8')
        rel = path.relative_to(ROOT)
        if path.name != 'opfin_http.dart' and direct_pattern.search(text):
            fail(f'{rel}: direct package:http call bypasses OpFinHttp', errors)
        for host in url_pattern.findall(text):
            if host.lower() not in hosts:
                fail(f'{rel}: literal client host {host} is outside whitelist manifest', errors)

    offline = (CLIENT / 'services' / 'offline_sync_service.dart').read_text(encoding='utf-8')
    batch = re.search(r'maxBatchBytes\s*=\s*(\d+)\s*\*\s*1024', offline)
    if not batch or int(batch.group(1)) > 256:
        fail('offline sync batch budget must be <= 256 KB', errors)
    count = re.search(r'maxEventsPerBatch\s*=\s*(\d+)', offline)
    if not count or int(count.group(1)) > 50:
        fail('offline sync event count must be <= 50', errors)

    if errors:
        print('Sponsored-data governance check FAILED:')
        for error in errors:
            print(f' - {error}')
        return 1
    print(f'Sponsored-data governance check passed for {len(hosts)} whitelisted client host(s).')
    return 0

if __name__ == '__main__':
    sys.exit(main())
