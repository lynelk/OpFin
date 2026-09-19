#!/usr/bin/env python3
"""Search tracked OpFin documentation, excluding historical evidence by default."""
import argparse
import json
import sys
from docs_toolkit import documents


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('query')
    parser.add_argument('--api', action='store_true')
    parser.add_argument('--history', action='store_true', help='Include dated audit/demo/migration evidence.')
    parser.add_argument('--limit', type=int, default=40)
    parser.add_argument('--json', action='store_true')
    args = parser.parse_args()
    words = args.query.casefold().split()
    if not words or args.limit < 1:
        parser.error('Provide a non-empty query and a positive limit.')
    try:
        hits = []
        for d in documents():
            if d['historical'] and not args.history:
                continue
            if args.api and not d['path'].startswith('apps/api/docs/'):
                continue
            haystack = (d['title'] + ' ' + d['path'] + ' ' + d['text']).casefold()
            if all(w in haystack for w in words):
                d['score'] = sum(4 * (w in d['title'].casefold()) + 2 * (w in d['path'].casefold()) for w in words)
                d['excerpts'] = [f'L{i}: {line.strip()}' for i, line in enumerate(d['text'].splitlines(), 1)
                                 if any(w in line.casefold() for w in words)][:3]
                hits.append(d)
        hits.sort(key=lambda d: (-d['score'], d['path']))
        if args.json:
            print(json.dumps([{k: v for k, v in d.items() if k != 'text'} for d in hits[:args.limit]], indent=2))
        else:
            for d in hits[:args.limit]:
                print(f"\n{d['path']} | {d['title']}" + (' [HISTORICAL]' if d['historical'] else ''))
                print('\n'.join('  ' + line for line in d['excerpts']))
            print(f'\n{len(hits)} matching documents; {min(len(hits), args.limit)} shown.')
        return 0 if hits else 1
    except (ValueError, OSError) as exc:
        print(str(exc), file=sys.stderr)
        return 2


if __name__ == '__main__':
    raise SystemExit(main())
