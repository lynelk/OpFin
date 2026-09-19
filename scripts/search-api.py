#!/usr/bin/env python3
"""Search registered non-demo API routes, or an explicitly identified export."""
import argparse
import json
import subprocess
import sys
from pathlib import Path
from docs_toolkit import ROOT, normalise_routes


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('query', help='Words such as credit, receipt, wallet or umra.')
    parser.add_argument('--snapshot', type=Path, help='Offline api-routes.json export; provenance is printed.')
    args = parser.parse_args()
    words = args.query.casefold().split()
    if not words:
        parser.error('query must contain at least one word')
    try:
        if args.snapshot:
            data = json.loads(args.snapshot.read_text(encoding='utf-8'))
            if not isinstance(data, dict) or data.get('schema_version') != 1 or not data.get('source_commit'):
                raise ValueError('Not a versioned OpFin API route export.')
            routes = normalise_routes(data['routes'])
            print(f"Snapshot: {data['source_commit']} | registration environment: {data.get('registered_environment', 'unknown')}")
            print('This is saved source evidence, not a live production check.')
        else:
            result = subprocess.run(['php', 'artisan', 'route:list', '--json'], cwd=ROOT / 'apps/api',
                                    check=True, capture_output=True, text=True, timeout=60)
            routes = normalise_routes(json.loads(result.stdout))
            print('Current checkout registration; feature gates and controller authorisation still apply.')
        hits = [r for r in routes if all(w in json.dumps(r).casefold() for w in words)]
        for r in hits:
            print(f"{r['method']:<8} {r['uri']:<65} {r['action']}")
            print('         Middleware: ' + ', '.join(r['middleware']))
        print(f'{len(hits)} matching API operations.')
        return 0 if hits else 1
    except (OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError) as exc:
        print(f'API discovery failed: {exc}', file=sys.stderr)
        print('Check PHP and composer install in apps/api, or supply a verified --snapshot.', file=sys.stderr)
        return 2


if __name__ == '__main__':
    raise SystemExit(main())
