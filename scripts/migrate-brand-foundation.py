#!/usr/bin/env python3
"""Idempotent presentation-only migration to the shared OpFin brand system."""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
changed = []

def write(path, content):
    if path.read_text() != content:
        path.write_text(content)
        changed.append(str(path.relative_to(ROOT)))

for path in (ROOT / 'apps/client/lib').glob('*.dart'):
    if path.name in ('main.dart', 'splash_screen.dart', 'constants.dart'):
        continue
    text = path.read_text()
    # Preserve status-specific colours and every API/financial control flow.
    text = text.replace('backgroundColor: Colors.black,', 'backgroundColor: OpFinColors.indigo,')
    text = text.replace('selectedItemColor: Colors.black,', 'selectedItemColor: OpFinColors.indigo,')
    replacements = {
        'Colors.black87': 'OpFinColors.ink', 'Colors.black54': 'OpFinColors.muted',
        'Colors.black45': 'OpFinColors.muted', 'Colors.black38': 'OpFinColors.muted',
        'Colors.black26': 'OpFinColors.line', 'Colors.black12': 'OpFinColors.line',
        'Colors.black': 'OpFinColors.ink',
    }
    for old, new in replacements.items():
        text = text.replace(old, new)
    if 'OpFinColors.' in text and "package:opfin/brand/brand_colors.dart" not in text:
        text = "import 'package:opfin/brand/brand_colors.dart';\n" + text
    write(path, text)

pubspec = ROOT / 'apps/client/pubspec.yaml'
text = pubspec.read_text()
if '    - assets/brand/' not in text:
    text = text.replace('    - assets/lottie/', '    - assets/lottie/\n    - assets/brand/')
if 'family: Inter' not in text:
    text = text.replace('\nflutter_icons:', '\n  fonts:\n    - family: Inter\n      fonts:\n' + ''.join(f'        - asset: assets/brand/InterVariable.ttf\n          weight: {weight}\n' for weight in (400, 500, 600, 700)) + '\nflutter_icons:')
text = text.replace('image_path: "assets/logo.png"', 'image_path: "assets/brand/opfin-app-icon.png"')
write(pubspec, text)

page = ROOT / 'apps/web/src/app/page.tsx'
text = page.read_text()
if 'import { OpFinSymbol }' not in text:
    text = 'import { OpFinSymbol } from "@/components/OpFinSymbol";\n' + text
text = text.replace('<span className="marketing-brand-mark">O</span>', '<span className="marketing-brand-mark"><OpFinSymbol /></span>')
text = text.replace('<h1>One place to move your money forward.</h1>', '<h1>Your next step, clearer.</h1>')
text = text.replace('aria-label="OpFin product preview"', 'aria-label="Illustrative OpFin product preview, not a production screenshot"')
if 'marketing-preview-disclosure' not in text:
    text = text.replace('<div className="marketing-orbit marketing-orbit-one" />', '<p className="marketing-preview-disclosure">Illustrative preview. Services depend on eligibility and availability.</p>\n          <div className="marketing-orbit marketing-orbit-one" />')
if 'href="https://opfin-production.up.railway.app/privacy-policy"' not in text:
    text = text.replace('</footer>', '<div className="marketing-legal-links"><a href="https://opfin-production.up.railway.app/privacy-policy">Privacy policy</a><Link href="/account/delete">Delete account</Link></div>\n      </footer>')
write(page, text)

shell = ROOT / 'apps/web/src/components/AppShell.tsx'
text = shell.read_text()
if 'import { OpFinSymbol }' not in text:
    text = 'import { OpFinSymbol } from "./OpFinSymbol";\n' + text
text = text.replace('<span className="brand-mark">OF</span>', '<span className="brand-mark"><OpFinSymbol reverse /></span>')
text = text.replace('>Switch role</button>', '>Sign out</button>')
write(shell, text)

ci = ROOT / '.github/workflows/ci.yml'
text = ci.read_text()
if 'concurrency:' not in text:
    text = text.replace('\njobs:', '\nconcurrency:\n  group: opfin-ci-${{ github.workflow }}-${{ github.ref }}\n  cancel-in-progress: true\n\njobs:')
text = text.replace('      - run: sh scripts/verify-layout.sh', '      - run: sh scripts/verify-layout.sh\n      - run: python3 scripts/sync-brand.py --check\n      - run: python3 scripts/verify-security-controls.py')
text = text.replace('          npm ci\n          npm run build', '          npm ci\n          npm audit --audit-level=high\n          npm run build')
text = text.replace('https://opfin-api-production.up.railway.app/api', 'https://opfin-production.up.railway.app/api')
# Emit a genuinely failing release gate when another job fails, is cancelled or is skipped.
text = text.replace('  release-gate:\n    needs:', '  release-gate:\n    if: always()\n    needs:')
text = text.replace('      - name: Record validated canonical SHA\n        run: echo "CANONICAL_OPFIN_SHA=$GITHUB_SHA"', '''      - name: Require every release prerequisite to pass
        env:
          JOB_RESULTS: ${{ toJSON(needs) }}
        run: |
          python3 - <<'PY'
          import json, os, sys
          jobs = json.loads(os.environ['JOB_RESULTS'])
          failed = {name: job['result'] for name, job in jobs.items() if job['result'] != 'success'}
          if failed:
              print('Release blocked:', failed)
              sys.exit(1)
          print('CANONICAL_OPFIN_SHA=' + os.environ['GITHUB_SHA'])
          PY''')
write(ci, text)
print('Presentation and release-control migration:', ', '.join(changed) if changed else 'already applied')
