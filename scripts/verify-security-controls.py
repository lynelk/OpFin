#!/usr/bin/env python3
"""Fail closed when the repository's documented security/brand controls drift."""
import hashlib
import json
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
errors = []

def require(condition, message):
    if not condition:
        errors.append(message)

def version(value):
    if not isinstance(value, str) or not re.fullmatch(r'\d+\.\d+\.\d+', value):
        return (-1, -1, -1)
    return tuple(map(int, value.split('.')))

package = json.loads((ROOT / 'apps/web/package.json').read_text())
lock = json.loads((ROOT / 'apps/web/package-lock.json').read_text())
resolved = lock.get('packages', {}).get('node_modules/sharp', {}).get('version')
require(version(package.get('overrides', {}).get('sharp')) >= (0, 35, 4), 'sharp override is below the reviewed security floor')
require(version(resolved) >= (0, 35, 4), 'sharp lockfile is below the reviewed security floor')
require(resolved == package.get('overrides', {}).get('sharp'), 'sharp override and lockfile do not agree')
require('npm run audit' in package.get('scripts', {}).get('check', ''), 'Combined web check must include the dependency audit')

ns = '{http://schemas.android.com/apk/res/android}'
manifest = ET.parse(ROOT / 'apps/client/android/app/src/main/AndroidManifest.xml').getroot()
app = manifest.find('application')
require(app is not None, 'Android application configuration is missing')
if app is not None:
    require(app.get(ns + 'allowBackup') == 'false', 'Android backup must be disabled')
    require(app.get(ns + 'usesCleartextTraffic') == 'false', 'Android cleartext traffic must be disabled')
    require(app.get(ns + 'dataExtractionRules') == '@xml/data_extraction_rules', 'Android extraction exclusions must remain active')
    require(app.get(ns + 'fullBackupContent') == '@xml/backup_rules', 'Legacy Android backup exclusions must remain active')
for name, sections in [('backup_rules.xml', [None]), ('data_extraction_rules.xml', ['cloud-backup', 'device-transfer'])]:
    document = ET.parse(ROOT / 'apps/client/android/app/src/main/res/xml' / name).getroot()
    for section in sections:
        node = document if section is None else document.find(section)
        require(node is not None, f'Missing {name} section {section}')
        excluded = {item.get('domain') for item in node.findall('exclude') if item.get('path') == '.'} if node is not None else set()
        require({'root', 'file', 'database', 'sharedpref', 'external', 'device_root', 'device_file', 'device_database', 'device_sharedpref'} <= excluded, f'Incomplete private-data exclusions in {name}/{section}')

layout = (ROOT / 'apps/web/src/app/layout.tsx').read_text()
require('await connection()' in layout, 'Nonce-bearing HTML must be rendered per request')
for style in ('brand-tokens.css', 'opfin-brand.css'):
    require(style in layout, f'Web brand stylesheet not loaded: {style}')
require('OpFinTheme.light' in (ROOT / 'apps/client/lib/main.dart').read_text(), 'Mobile must load the canonical OpFin theme')
for path in (ROOT / 'apps/client/lib').glob('*.dart'):
    require('Colors.black' not in path.read_text(), f'Legacy unthemed mobile black override: {path.name}')

brand_tokens = json.loads((ROOT / 'brand/opfin.tokens.json').read_text())
require(brand_tokens.get('version', '').startswith('3.0.0-rc.'), 'Brand token source must remain on the controlled v3 release candidate until freeze gates pass')
require(brand_tokens.get('logo', {}).get('source') == 'brand/v3/assets/opfin-symbol-master.svg', 'Web/mobile brand source must point to the v3 vector master')
require(brand_tokens.get('appIcon', {}).get('source') == 'brand/v3/assets/opfin-app-icon-master.svg', 'App icon must point to the v3 vector master')

legacy_web_tokens = ('#0f766e', '#115e59', '#0b1f3a', '#dff7f2', '#f4f8f7', '#d7e1df', 'arial, helvetica')
for relative in ('apps/web/src/app/globals.css', 'apps/web/src/app/marketing.css', 'apps/web/src/app/experience.css'):
    css = (ROOT / relative).read_text().lower()
    for retired in legacy_web_tokens:
        require(retired not in css, f'Legacy colour/type token {retired} remains in {relative}')

symbol_component = (ROOT / 'apps/web/src/components/OpFinSymbol.tsx').read_text()
require('/brand/opfin-symbol.svg' in symbol_component, 'Web must use the v3 vector monogram')
require('/brand/opfin-symbol-reverse.svg' in symbol_component, 'Web reverse mark must use the v3 vector monogram')

web_dashboard = (ROOT / 'apps/web/src/app/(portal)/dashboard/page.tsx').read_text()
mobile_home = (ROOT / 'apps/client/lib/home_screen.dart').read_text()
for label, text in (('Web dashboard', web_dashboard), ('mobile Home', mobile_home)):
    require('Financial Compass' in text, f'{label} must retain the Financial Compass pattern')
    require('Next Step' in text or 'NEXT STEP' in text or 'Recommended next step' in text, f'{label} must retain the Next Step pattern')

assets = json.loads((ROOT / 'brand/asset-manifest.json').read_text())
for path, expected in assets['files'].items():
    target = (ROOT / path).resolve()
    require(target.is_relative_to(ROOT), 'Invalid asset path')
    require(target.is_file() and hashlib.sha256(target.read_bytes()).hexdigest() == expected, f'Brand asset changed without provenance update: {path}')
for path in (ROOT / 'apps/client/lib').rglob('*.dart'):
    text = path.read_text()
    require(not re.search(r'badCertificateCallback\s*=', text), f'TLS certificate bypass in {path.relative_to(ROOT)}')

if errors:
    print('\n'.join('Control failure: ' + item for item in errors), file=sys.stderr)
    sys.exit(1)
print('Dependency floor, mobile transport/backup, nonce rendering and OpFin v3 brand provenance checks passed.')
