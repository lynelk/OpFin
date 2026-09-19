#!/usr/bin/env python3
"""Require relevant, surviving documentation alongside changed runtime contracts."""
from __future__ import annotations
import argparse
from pathlib import Path
import sys
from docs_toolkit import ROOT, git, historical

REQUIRED_CURRENT_DOCS = [
    'README.md', 'AGENTS.md', 'SECURITY.md', 'CONTRIBUTING.md', 'docs/README.md',
    'docs/DEVELOPER_START_HERE.md', 'docs/DOCUMENTATION_MAINTENANCE.md',
    'docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md', 'docs/LAUNCH_CUSTOMER_JOURNEY.md',
    'docs/UMRA_DIGITAL_LENDING_CONTROLS.md', 'apps/api/README.md', 'apps/api/docs/README.md',
    'apps/api/docs/api/API_QUICK_REFERENCE.md', 'apps/api/docs/api/current-endpoints.md',
    'apps/api/docs/api/frontend-backend-contract.md', 'apps/api/docs/api/INTEGRATOR_GUIDE.md',
    'apps/client/README.md', 'apps/web/README.md',
]
FORBIDDEN_CURRENT_PHRASES = {
    'apps/client/README.md': ['A new Flutter project.'],
    'apps/web/README.md': ['Home | Borrow | Save | Grow | More', 'lynelk/OpFin-BE'],
    'apps/web/docs/api/frontend-backend-contract.md': ['"password": "password"', 'investor-demo screens'],
    'apps/web/docs/frontend/screen-map.md': ['phone/password login', 'backend offer module is missing'],
    'packages/contracts/README.md': ['initial migration deliberately does not invent'],
}


def change_errors(changes: set[str], surviving_docs: set[str]) -> list[str]:
    docs = {p for p in surviving_docs if p.endswith('.md') and not historical(p)}
    errors = []
    def changed(*prefixes):
        return any(p.startswith(prefixes) for p in changes)
    def documented(*prefixes):
        return any(p.startswith(prefixes) for p in docs)
    if changed('apps/api/routes/') and 'apps/api/docs/api/current-endpoints.md' not in docs:
        errors.append('API routes changed: update apps/api/docs/api/current-endpoints.md.')
    if changed('apps/api/app/', 'apps/api/config/', 'apps/api/bootstrap/', 'apps/api/database/migrations/') and not documented(
        'apps/api/docs/api/', 'apps/api/docs/architecture/', 'apps/api/docs/operations/',
        'apps/api/docs/integrations/', 'apps/api/docs/production/', 'apps/api/docs/uat/',
        'docs/LAUNCH_CUSTOMER_JOURNEY.md', 'docs/UMRA_DIGITAL_LENDING_CONTROLS.md'):
        errors.append('API behaviour/configuration changed without a relevant API, architecture, operations or UAT update.')
    if changed('packages/contracts/') and any(not p.endswith('.md') and p.startswith('packages/contracts/') for p in changes) and not documented(
        'apps/api/docs/api/', 'packages/contracts/README.md'):
        errors.append('Shared contracts changed without API/contract documentation.')
    if changed('apps/client/lib/') and not documented(
        'apps/client/README.md', 'docs/LAUNCH_CUSTOMER_JOURNEY.md', 'docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md', 'docs/manuals/'):
        errors.append('Flutter journey changed without client, journey or training documentation.')
    if changed('apps/web/src/') and not documented(
        'apps/web/README.md', 'apps/web/docs/', 'docs/LAUNCH_CUSTOMER_JOURNEY.md', 'docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md', 'docs/manuals/'):
        errors.append('Web/admin workflow changed without current web, journey or training documentation.')
    setup_files = {'apps/api/composer.json', 'apps/api/composer.lock', 'apps/api/.env.example',
                   'apps/web/package.json', 'apps/web/package-lock.json', 'apps/web/.env.example',
                   'apps/client/pubspec.yaml', 'apps/client/pubspec.lock'}
    if changes & setup_files and not documented('docs/DEVELOPER_START_HERE.md', 'apps/api/README.md', 'apps/web/README.md', 'apps/client/README.md'):
        errors.append('Dependency/environment setup changed without a component README or developer setup review.')
    if changed('.github/workflows/', 'infrastructure/', 'apps/api/railway/') and not documented(
        'CONTRIBUTING.md', 'SECURITY.md', 'docs/DOCUMENTATION_MAINTENANCE.md', 'docs/DEVELOPER_START_HERE.md',
        'infrastructure/', 'apps/api/docs/operations/', 'apps/api/docs/production/'):
        errors.append('Release/deployment tooling changed without current contributor, security or operational guidance.')
    return errors


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base', help='Exact comparison ref. Includes tracked working-tree edits; stage new docs first.')
    args = parser.parse_args()
    errors = []
    try:
        for relative in REQUIRED_CURRENT_DOCS:
            path = ROOT / relative
            if not path.is_file() or path.stat().st_size < 80:
                errors.append('Missing or empty current documentation: ' + relative)
        for relative, phrases in FORBIDDEN_CURRENT_PHRASES.items():
            path = ROOT / relative
            if path.is_file():
                text = path.read_text(encoding='utf-8').casefold()
                errors += [f'Stale phrase in {relative}: {phrase}' for phrase in phrases if phrase.casefold() in text]
        if args.base:
            # Resolve first: a missing/shallow base must fail, never silently skip the check.
            base = git(ROOT, 'rev-parse', '--verify', args.base + '^{commit}').strip()
            changes = set(filter(None, git(ROOT, 'diff', '--name-only', '-z', base, '--').split('\0')))
            surviving = {p for p in changes if (ROOT / p).is_file() and (ROOT / p).stat().st_size >= 80}
            errors.extend(change_errors(changes, surviving))
            print(f'Compared {len(changes)} changed tracked paths against {base}.')
        else:
            print('Content checks only. Change-impact comparison NOT RUN; supply --base <ref>.')
    except (ValueError, OSError) as exc:
        print(str(exc), file=sys.stderr)
        return 2
    for error in errors:
        print('ERROR: ' + error)
    print('Documentation checks failed.' if errors else 'Requested documentation checks passed.')
    return int(bool(errors))


if __name__ == '__main__':
    raise SystemExit(main())
