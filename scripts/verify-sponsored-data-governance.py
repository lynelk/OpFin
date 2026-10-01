#!/usr/bin/env python3
import json
import re
import sys
from pathlib import Path
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / 'distribution' / 'sponsored-data' / 'whitelist-manifest.json'
CLIENT = ROOT / 'apps' / 'client' / 'lib'

def fail(message: str, errors: list[str]) -> None:
    errors.append(message)

def main() -> int:
    errors: list[str] = []
    data = json.loads(MANIFEST.read_text(encoding='utf-8'))
    rows = [row for row in data.get('client_whitelist_hosts', []) if row.get('host')]
    hosts = {row['host'].lower() for row in rows}
    origins = {
        f"{str(row.get('scheme', 'https')).lower()}://{row['host'].lower()}:{int(port)}"
        for row in rows
        for port in (row.get('ports') or [443 if str(row.get('scheme', 'https')).lower() == 'https' else 80])
    }
    if not hosts:
        fail('whitelist manifest has no client_whitelist_hosts', errors)
    if data.get('status') not in {'candidate', 'approved'}:
        fail('whitelist manifest status must be candidate or approved', errors)
    approval = data.get('approval') or {}
    if data.get('status') == 'approved' and not approval.get('operator_reference'):
        fail('approved sponsorship requires an operator_reference', errors)

    constants = (CLIENT / 'constants.dart').read_text(encoding='utf-8')
    match = re.search(r"defaultValue:\s*'(https?://[^']+)", constants)
    if not match:
        fail('cannot determine Flutter default API origin', errors)
    else:
        parsed = urlsplit(match.group(1))
        effective_port = parsed.port or (443 if parsed.scheme.lower() == 'https' else 80)
        default_origin = f"{parsed.scheme.lower()}://{parsed.hostname.lower()}:{effective_port}" if parsed.hostname else ''
        if default_origin not in origins:
            fail(f"Flutter default API origin {default_origin} is absent from whitelist manifest", errors)

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
    print(f'Sponsored-data governance check passed for {len(origins)} whitelisted client origin(s).')
    return 0

if __name__ == '__main__':
    sys.exit(main())
