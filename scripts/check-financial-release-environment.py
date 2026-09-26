#!/usr/bin/env python3
"""Inspect local release prerequisites only. Never contact a provider or execute migrations."""
from __future__ import annotations
import argparse
import datetime as dt
import json
import pathlib
import shutil
import subprocess
import sys


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=pathlib.Path, default=pathlib.Path(__file__).resolve().parents[1])
    args = parser.parse_args()
    root = args.root.resolve()
    requirements: dict[str, bool] = {}
    revision = None
    if shutil.which('git'):
        proc = subprocess.run(['git', '-C', str(root), 'rev-parse', 'HEAD'], capture_output=True, text=True, timeout=10)
        if proc.returncode == 0 and len(proc.stdout.strip()) == 40:
            revision = proc.stdout.strip()
    requirements['exact_git_checkout'] = revision is not None
    required_files = ['apps/api/artisan', 'apps/api/composer.lock', 'apps/api/vendor/autoload.php',
                      'apps/web/package.json', 'apps/web/node_modules/.bin/next',
                      'apps/web/node_modules/.bin/tsc', 'apps/client/pubspec.lock',
                      'apps/client/.dart_tool/package_config.json']
    for name in required_files:
        requirements[name] = (root / name).exists()
    for tool in ['php', 'composer', 'node', 'flutter', 'dart', 'psql']:
        requirements['tool:' + tool] = shutil.which(tool) is not None
    modules: set[str] = set()
    if shutil.which('php'):
        result = subprocess.run(['php', '-m'], capture_output=True, text=True, timeout=10)
        if result.returncode == 0:
            modules = {line.strip().lower() for line in result.stdout.splitlines()}
    for name in ['pdo_sqlite', 'pdo_pgsql', 'mbstring', 'dom', 'openssl']:
        requirements['php-extension:' + name] = name in modules
    requirements['no_cached_application_configuration'] = not (root / 'apps/api/bootstrap/cache/config.php').exists()
    missing = [name for name, available in requirements.items() if not available]
    report = {
        'checked_at_utc': dt.datetime.now(dt.timezone.utc).isoformat(),
        'source_revision': revision,
        'preflight_ready': not missing,
        'missing_prerequisites': missing,
        'requirements': requirements,
        'scope': 'Local toolchain and dependency presence only. No tests, audits, migrations, provider calls or deployment were performed by this command.',
        'remaining_acceptance': [
            'Full existing API tests plus new collection tests on SQLite and isolated PostgreSQL.',
            'Repeated clean migration and upgrade-path tests with immutable evidence preserved.',
            'Changed PHP formatting, Composer audit and repository documentation checks.',
            'Full Next.js type check, tests and production build on the exact candidate.',
            'Flutter format, analyse, unit/widget tests and authorised release compilation.',
            'Device/accessibility and interrupted-request recovery acceptance.',
            'Independent approved financial review and exact-source deployment evidence.',
        ],
    }
    print(json.dumps(report, indent=2))
    return 0 if not missing else 2


if __name__ == '__main__':
    sys.exit(main())
