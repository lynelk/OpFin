#!/usr/bin/env python3
"""Build a read-only, offline documentation portal and evidence-labelled API index.

Standard library only. Never executes Markdown, calls an API endpoint or publishes a site.
"""
from __future__ import annotations

import argparse
import hashlib
import html
import json
import re
import subprocess
import sys
from pathlib import Path
from urllib.parse import quote, unquote, urlsplit

ROOT = Path(__file__).resolve().parents[1]
EXCLUDED = {'.git', '.build', 'node_modules', 'vendor', '.dart_tool', 'build', '__pycache__'}
HISTORICAL = {'audit', 'demo', 'migration', 'archive', 'releases', 'checkpoints'}
HTTP_METHODS = {'GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'}


def git(root: Path, *args: str) -> str:
    result = subprocess.run(['git', *args], cwd=root, capture_output=True, text=True, timeout=30)
    if result.returncode:
        raise ValueError(f"Git command failed ({' '.join(args)}): {result.stderr.strip()}")
    return result.stdout


def historical(path: str) -> bool:
    p = Path(path)
    return bool(set(p.parts) & HISTORICAL) or 'checkpoint' in p.stem or bool(re.search(r'20\d{2}-\d{2}-\d{2}', p.name))


def documents(root: Path = ROOT) -> list[dict]:
    result = []
    for name in sorted(filter(None, git(root, 'ls-files', '-z', '--', '*.md').split('\0'))):
        path = root / name
        if set(path.parts) & EXCLUDED or path.is_symlink() or not path.is_file():
            continue
        if not path.resolve().is_relative_to(root.resolve()):
            continue
        text = path.read_text(encoding='utf-8')
        title = next((line[2:].strip() for line in text.splitlines() if line.startswith('# ')), path.stem)
        result.append({'path': name, 'title': title, 'historical': historical(name), 'text': text,
                       'sha256': hashlib.sha256(text.encode()).hexdigest()})
    return result


def provenance(root: Path = ROOT) -> dict:
    return {'source_commit': git(root, 'rev-parse', 'HEAD').strip(),
            'dirty_worktree': bool(git(root, 'status', '--porcelain', '--untracked-files=no').strip()),
            'availability': 'Repository evidence only; deployed availability is not certified.'}


def unfenced(text: str) -> str:
    return re.sub(r'(?ms)^\s*(`{3,}|~{3,})[^\n]*\n.*?^\s*\1\s*$', '', text)


def link_errors(root: Path, doc: dict) -> list[str]:
    """Check local file targets. Anchor checks are intentionally not implied."""
    text = unfenced(doc['text'])
    links = re.findall(r'!?\[[^\]\n]*\]\(<?([^\s)>]+)>?(?:\s+["\'][^\n]*["\'])?\)', text)
    links += re.findall(r'(?m)^\s*\[[^\]]+\]:\s*<?([^\s>]+)>?', text)
    errors = []
    for target in links:
        url = urlsplit(target)
        if url.scheme or url.netloc or not url.path:
            continue
        decoded = unquote(url.path)
        path = ((root / decoded.lstrip('/')) if decoded.startswith('/') else
                root / Path(doc['path']).parent / decoded).resolve()
        if not path.is_relative_to(root.resolve()) or not path.exists():
            errors.append(f"{doc['path']}: missing local target {target}")
    return errors


def normalise_uri(uri: str) -> str:
    return urlsplit(re.sub(r'\{[^}]+\}', '{}', '/' + uri.lstrip('/'))).path.rstrip('/') or '/'


def normalise_routes(raw: object) -> list[dict]:
    if not isinstance(raw, list) or not raw:
        raise ValueError('Expected a non-empty Laravel route:list --json array.')
    routes, seen = [], set()
    for item in raw:
        if not isinstance(item, dict) or not isinstance(item.get('uri'), str) or not isinstance(item.get('method'), str):
            raise ValueError('A route is missing a string uri or method.')
        uri = '/' + item['uri'].lstrip('/')
        if not uri.startswith('/api/') or uri.startswith('/api/demo/'):
            continue
        middleware = item.get('middleware', [])
        if isinstance(middleware, str):
            middleware = [middleware]
        if not isinstance(middleware, list) or not all(isinstance(m, str) for m in middleware):
            raise ValueError(f'Invalid middleware for {uri}.')
        for method in item['method'].split('|'):
            if method not in HTTP_METHODS:
                raise ValueError(f'Unrecognised method {method}.')
            if method == 'HEAD':
                continue
            domain = item.get('domain') or ''
            key = (domain, method, uri)
            if key in seen:
                raise ValueError(f'Duplicate route: {key}')
            seen.add(key)
            routes.append({'method': method, 'uri': uri, 'domain': domain,
                           'name': item.get('name') or '', 'action': item.get('action') or '',
                           'middleware': sorted(middleware)})
    if not routes:
        raise ValueError('No non-demo /api routes were found; refusing an empty export.')
    return sorted(routes, key=lambda r: (r['uri'], r['method'], r['domain']))


def documented_routes(text: str) -> set[tuple[str, str]]:
    found = set()
    for line in unfenced(text).splitlines():
        match = re.search(r'\|\s*(GET|POST|PUT|PATCH|DELETE|OPTIONS)(?:/(GET|POST|PUT|PATCH|DELETE|OPTIONS))?\s*\|\s*`(/api/[^`]+)`', line)
        inline = re.search(r'`((?:GET|POST|PUT|PATCH|DELETE|OPTIONS)(?:/(?:GET|POST|PUT|PATCH|DELETE|OPTIONS))*)\s+(/api/[^`]+)`', line)
        if match:
            for method in filter(None, match.group(1, 2)):
                found.add((method, normalise_uri(match.group(3))))
        elif inline:
            for method in inline.group(1).split('/'):
                found.add((method, normalise_uri(inline.group(2))))
    return found


def export_api(root: Path, route_file: Path, out: Path, environment: str) -> dict:
    routes = normalise_routes(json.loads(route_file.read_text(encoding='utf-8')))
    known = {(r['method'], normalise_uri(r['uri'])) for r in routes}
    described, invalid = set(), []
    for relative in ('apps/api/docs/api/current-endpoints.md', 'apps/api/docs/api/API_QUICK_REFERENCE.md'):
        path = root / relative
        if not path.is_file():
            raise ValueError(f'Missing API narrative: {relative}')
        entries = documented_routes(path.read_text(encoding='utf-8'))
        described |= entries
        invalid += [f'{relative}: {method} {uri}' for method, uri in sorted(entries - known)]
    if invalid:
        raise ValueError('Documented routes not registered in this export:\n' + '\n'.join(invalid))
    result = {'schema_version': 1, **provenance(root), 'registered_environment': environment,
              'scope': 'Non-demo /api routes; implicit HEAD omitted. Not an OpenAPI schema.',
              'routes': routes, 'narrative_uncovered': [list(x) for x in sorted(known - described)]}
    out.mkdir(parents=True, exist_ok=True)
    (out / 'api-routes.json').write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
    def cell(value: object) -> str:
        return str(value).replace('|', '\\|').replace('\n', ' ')
    lines = ['# Generated API route reference', '', f"Source commit: `{result['source_commit']}`", '',
             f'Registration environment: `{environment}`. ' + result['availability'], '',
             'Generated from Laravel, not inferred from route-source regular expressions. Middleware is registration metadata, not a complete authorisation policy. Controller checks and feature gates still apply.', '',
             'This is a discovery index, not a complete request/response schema or permission certificate. Read the integrator guide and controller tests before using an operation.', '',
             '| Method | Route | Handler | Registered middleware |', '| --- | --- | --- | --- |']
    for r in routes:
        lines.append('| ' + ' | '.join(cell(x) for x in (r['method'], r['uri'], r['action'], ', '.join(r['middleware']))) + ' |')
    lines += ['', '## Narrative coverage gaps', '',
              'These registered operations do not appear in the two curated endpoint tables. The generated index covers discovery only; maintainers must add purpose, validation, errors and examples before treating an integration as fully documented.', '']
    lines += [f'- `{method} {uri}`' for method, uri in result['narrative_uncovered']]
    (out / 'API_ROUTE_REFERENCE.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')
    return result


PAGE = '''<!doctype html><html lang="en-GB"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'sha256-__SCRIPT_HASH__'; style-src 'sha256-__STYLE_HASH__'; base-uri 'none'; form-action 'none'">
<title>OpFin documentation</title><style>__CSS__</style>
<header><h1>OpFin documentation</h1><p>Product, training, operations and developer reference</p><p id="version"></p></header>
<main><section aria-label="Search documentation"><label for="query">Search all words, topics, endpoints or filenames</label><input id="query" type="search" placeholder="Try: repayment, KYC, PIN, API" autocomplete="off">
<label><input id="history" type="checkbox"> Include historical evidence</label><p id="count" role="status" aria-live="polite"></p><nav id="results" aria-label="Search results"></nav></section>
<article id="document" aria-label="Document contents" tabindex="-1"><h2>Select a document</h2><p>Search runs entirely in this browser. No API calls, credentials, analytics or external assets are used.</p><p>Current reference does not mean production-verified. Historical entries are excluded by default.</p></article></main>
<script>__JS__</script></html>'''
CSS = '''body{margin:0;font:17px/1.6 system-ui,sans-serif;background:#FAF8F2;color:#20243C}header{background:#353B78;color:white;padding:1.4rem 4vw}header h1,header p{margin:.2rem 0}main{display:grid;grid-template-columns:minmax(260px,32%) 1fr;gap:2rem;padding:2rem 4vw}input[type=search]{box-sizing:border-box;width:100%;padding:.8rem;font:inherit;border:1px solid #353B78;border-radius:8px}label{display:block}button{display:block;text-align:left;width:100%;background:white;color:#20243C;border:1px solid #ccc;border-radius:8px;padding:.8rem;margin:.6rem 0;font:inherit;cursor:pointer}button:hover,button:focus-visible{outline:3px solid #353B78}small{display:block;overflow-wrap:anywhere}article{min-width:0}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:white;padding:1rem;border-radius:8px;font:14px/1.6 ui-monospace,monospace}h2{line-height:1.25}a{color:#353B78}#results{max-height:70vh;overflow:auto}#version{font-size:.85rem}@media(max-width:760px){main{display:block}#results{max-height:35vh}article{margin-top:2rem}}'''
JS = '''const data=__DATA__;const query=document.getElementById('query'),history=document.getElementById('history'),results=document.getElementById('results'),article=document.getElementById('document');
document.getElementById('version').textContent='Commit '+data.source_commit+(data.dirty_worktree?' (local changes)':'')+' | '+data.availability;
function show(d){article.replaceChildren();const h=document.createElement('h2');h.textContent=d.title;article.append(h);const meta=document.createElement('p');meta.textContent=(d.historical?'HISTORICAL EVIDENCE':'CURRENT REFERENCE, NOT RELEASE CERTIFICATION')+' | '+d.path;article.append(meta);if(!d.generated){const a=document.createElement('a');a.textContent='View source at this commit';a.href='https://github.com/lynelk/OpFin/blob/'+data.source_commit+'/'+d.path.split('/').map(encodeURIComponent).join('/');article.append(a);}const pre=document.createElement('pre');pre.textContent=d.text;article.append(pre);article.focus();}
function search(){const words=query.value.toLocaleLowerCase().trim().split(/\\s+/).filter(Boolean);const matches=data.documents.filter(d=>(history.checked||!d.historical)&&words.every(w=>(d.title+' '+d.path+' '+d.text).toLocaleLowerCase().includes(w))).sort((a,b)=>score(b,words)-score(a,words)||a.path.localeCompare(b.path));results.replaceChildren();document.getElementById('count').textContent=matches.length+' matching documents';matches.forEach(d=>{const b=document.createElement('button');b.type='button';b.textContent=d.title;const s=document.createElement('small');s.textContent=(d.historical?'Historical | ':'')+d.path;b.append(s);b.addEventListener('click',()=>show(d));results.append(b);});}
function score(d,words){return words.reduce((s,w)=>s+(d.title.toLocaleLowerCase().includes(w)?4:0)+(d.path.toLocaleLowerCase().includes(w)?2:0),0);}query.addEventListener('input',search);history.addEventListener('change',search);search();'''


def build(root: Path, out: Path, api_dir: Path | None = None) -> dict:
    docs = documents(root)
    meta = provenance(root)
    if api_dir:
        exported = json.loads((api_dir / 'api-routes.json').read_text(encoding='utf-8'))
        if not isinstance(exported, dict) or exported.get('source_commit') != meta['source_commit'] or exported.get('dirty_worktree') != meta['dirty_worktree']:
            raise ValueError('API export provenance differs from this checkout; regenerate it.')
        docs.append({'path': 'generated/API_ROUTE_REFERENCE.md', 'title': 'Generated API route reference',
                     'historical': False, 'generated': True,
                     'text': (api_dir / 'API_ROUTE_REFERENCE.md').read_text(encoding='utf-8')})
    data = {**meta, 'documents': docs}
    # Escape '<' so source Markdown cannot terminate the script element.
    payload = json.dumps(data, ensure_ascii=True).replace('<', '\\u003c')
    script = JS.replace('__DATA__', payload)
    import base64
    digest = lambda text: base64.b64encode(hashlib.sha256(text.encode()).digest()).decode()
    page = PAGE.replace('__SCRIPT_HASH__', digest(script)).replace('__STYLE_HASH__', digest(CSS))
    page = page.replace('__CSS__', CSS).replace('__JS__', script)
    out.mkdir(parents=True, exist_ok=True)
    (out / 'index.html').write_text(page, encoding='utf-8')
    inventory = {**meta, 'documents': [{k: v for k, v in d.items() if k != 'text'} for d in docs]}
    (out / 'documentation-inventory.json').write_text(json.dumps(inventory, indent=2) + '\n', encoding='utf-8')
    return inventory


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='command', required=True)
    b = sub.add_parser('build')
    b.add_argument('--output', type=Path, default=ROOT / '.build/docs')
    b.add_argument('--api-dir', type=Path)
    c = sub.add_parser('check')
    c.add_argument('--base', help='Fail on new local-link errors relative to this exact commit; report older errors.')
    a = sub.add_parser('export-api')
    a.add_argument('--routes', type=Path, required=True)
    a.add_argument('--environment', required=True, help='Environment used to run Laravel route:list, e.g. testing.')
    a.add_argument('--output', type=Path, default=ROOT / '.build/api')
    args = parser.parse_args()
    try:
        if args.command == 'build':
            result = build(ROOT, args.output, args.api_dir)
            print(f"Built {len(result['documents'])} documents at {args.output / 'index.html'}")
        elif args.command == 'export-api':
            result = export_api(ROOT, args.routes, args.output, args.environment)
            print(f"Exported {len(result['routes'])} routes; {len(result['narrative_uncovered'])} narrative coverage gaps.")
        else:
            docs = documents()
            errors = [e for d in docs if not d['historical'] for e in link_errors(ROOT, d)]
            changed = None
            if args.base:
                changed = set(filter(None, git(ROOT, 'diff', '--name-only', '-z', args.base, '--').split('\0')))
            # Existing reference defects remain visible; changed current docs must be clean.
            failures = [e for e in errors if changed is None or e.split(':', 1)[0] in changed]
            for error in errors:
                print(('ERROR: ' if error in failures else 'EXISTING: ') + error)
            print(f"Inventoried {len(docs)} Markdown files; {len(errors)} local-link defects; {len(failures)} blocking.")
            return int(bool(failures))
    except (OSError, ValueError, subprocess.TimeoutExpired) as exc:
        print(f'Documentation tooling failed: {exc}', file=sys.stderr)
        return 2
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
