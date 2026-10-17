#!/usr/bin/env python3
"""Redacting inspection of unique reachable Git blobs. Never prints secret content.

This is an incident triage aid, not credential rotation or certification.
Requires a full checkout with all relevant remote refs fetched.
"""
import re
import subprocess
import sys

patterns = {
    'private_key': rb'-----BEGIN (?:[A-Z0-9]+ )?PRIVATE KEY-----',
    'github_token': rb'(?:gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,})',
    'aws_access_key': rb'AKIA[0-9A-Z]{16}',
    'laravel_application_key': rb'APP_KEY=base64:[A-Za-z0-9+/]{40,}={0,2}',
    'live_secret': rb'sk_live_[A-Za-z0-9]{16,}',
}
restricted = ('.zip', '.tar', '.tar.gz', '.7z', '.pem', '.key', '.p12', '.pfx', '.sql', '.dump')
partial = subprocess.run(['git', 'config', '--get', 'extensions.partialclone'],
                         capture_output=True, text=True)
promisor = subprocess.run(['git', 'config', '--get', 'remote.origin.promisor'],
                         capture_output=True, text=True)
if (partial.returncode == 0 and partial.stdout.strip()) or promisor.stdout.strip() == 'true':
    sys.exit('Incomplete history scan: partial clone detected. Use a full, authorised clone; no clean result claimed.')
result = subprocess.run(['git', 'rev-list', '--objects', '--all'],
                        capture_output=True, check=True, timeout=180)
objects = {}
for line in result.stdout.splitlines():
    sha, _, path = line.partition(b' ')
    if path:
        objects.setdefault(sha.decode(), path.decode('utf-8', 'replace'))
sha_list = list(objects)
if not sha_list:
    sys.exit('No reachable named objects found; incomplete scan.')
batch = subprocess.Popen(['git', 'cat-file', '--batch'], stdin=subprocess.PIPE, stdout=subprocess.PIPE)
assert batch.stdin and batch.stdout
flags = {}
skipped = 0
scanned = 0
for sha in sha_list:
    batch.stdin.write(sha.encode()+b'\n')
    batch.stdin.flush()
    header = batch.stdout.readline().split()
    if len(header) != 3:
        raise RuntimeError('Could not read all object metadata.')
    obj_type, size = header[1], int(header[2])
    if obj_type != b'blob':
        remaining = size
        while remaining:
            remaining -= len(batch.stdout.read(min(65536, remaining)))
        batch.stdout.read(1)
        continue
    if size > 5_000_000:
        skipped += 1
    content = bytearray()
    remaining = size
    while remaining:
        piece = batch.stdout.read(min(65536, remaining))
        if not piece:
            raise RuntimeError('Unexpected end of Git blob stream.')
        if size <= 5_000_000:
            content.extend(piece)
        remaining -= len(piece)
    batch.stdout.read(1)
    scanned += 1
    name = objects[sha].lower()
    if name.endswith(restricted) or '/.env' in name or name.endswith('/.env'):
        flags['historical_sensitive_artifact'] = flags.get('historical_sensitive_artifact', 0)+1
    if content and b'\0' not in content[:1024]:
        for label, pat in patterns.items():
            if re.search(pat, content):
                flags[label] = flags.get(label, 0)+1
batch.stdin.close()
batch.wait()
print('Reachable-history triage: '+str(scanned)+' blobs inspected; '+str(skipped)+' oversized blobs uninspected.')
for label, count in sorted(flags.items()):
    print(label+': '+str(count)+' historical object(s) flagged (values redacted)')
if flags or skipped:
    print('Security closure requires confidential remediation review; DO NOT publish recovered credential values.')
    sys.exit(1)
print('No signatures detected by this limited scanner; still verify external refs, revoked secrets and archives.')
