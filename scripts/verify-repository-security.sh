#!/bin/sh
set -eu

# This gate checks the current Git index, not reachable history. Removing a file
# here does not revoke credentials or purge earlier commits, forks, or caches.
exec python3 - <<'PY'
import os
import subprocess
import sys
from pathlib import PurePosixPath


def git(*args):
    result = subprocess.run(['git', *args], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if result.returncode not in (0, 1) or (result.returncode == 1 and args[0] != 'grep'):
        print('Repository security verification failed: Git inspection error.', file=sys.stderr)
        sys.exit(2)
    return result


def report(label, raw_paths):
    for raw in raw_paths.split(b'\0'):
        if raw:
            # Log paths only: never echo secret-bearing lines into CI logs.
            print(f'{label}: {os.fsdecode(raw)!r}', file=sys.stderr)


failed = False
paths = git('ls-files', '-z').stdout
for raw in paths.split(b'\0'):
    if not raw:
        continue
    name = PurePosixPath(os.fsdecode(raw)).name.lower()
    environment = name == '.env' or name.startswith('.env.') or name.endswith('.env') or '.env.' in name
    example = name.endswith(('.example', '.sample', '.template'))
    reason = None
    if environment and not example:
        reason = 'Tracked environment file is not allowed'
    elif name.endswith(('.pem', '.key', '.p12', '.pfx', '.jks', '.keystore')):
        reason = 'Tracked private key/keystore material is not allowed'
    elif name.endswith(('.sql', '.dump', '.backup', '.sqlite', '.sqlite3', '.db', '.db3', '.sqlite-wal', '.sqlite-shm', '.sqlite3-wal', '.sqlite3-shm', '.db-wal', '.db-shm')):
        reason = 'Tracked database artifact is not allowed'
    elif name.endswith(('.zip', '.tar', '.tar.gz', '.tgz', '.7z')):
        reason = 'Tracked archive is not allowed; use release artifacts instead'
    if reason:
        report(reason, raw)
        failed = True

patterns = [
    ('private key', '-----BEGIN ([A-Z0-9]+ )?PRIVATE KEY-----'),
    ('GitHub classic token', 'gh[pousr]_[A-Za-z0-9]{20,}'),
    ('GitHub fine-grained token', 'github_pat_[A-Za-z0-9_]{20,}'),
    ('Stripe-style live secret', 'sk_live_[A-Za-z0-9]{16,}'),
    ('AWS access key', 'AKIA[0-9A-Z]{16}'),
    ('Slack token', 'xox[baprs]-[A-Za-z0-9-]{10,}'),
    ('non-placeholder Laravel application key', 'APP_KEY=base64:[A-Za-z0-9+/]{40,}={0,2}'),
]
for label, pattern in patterns:
    # -e protects leading-hyphen signatures. --cached checks tracked content,
    # including files that have been staged but removed from the working tree.
    matches = git('grep', '--cached', '-l', '-z', '-I', '-E', '-e', pattern, '--', '.')
    if matches.returncode == 0:
        report(f'Potential {label} in tracked source', matches.stdout)
        failed = True

if failed:
    print('Repository security verification failed. Remove prohibited material and rotate exposed credentials. Historical cleanup requires separate verification.', file=sys.stderr)
    sys.exit(1)
print('Repository security verification passed for the current index; reachable history is not certified.')
PY
