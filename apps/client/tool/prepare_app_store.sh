#!/usr/bin/env bash
set -euo pipefail

PROJECT="ios/Runner.xcodeproj/project.pbxproj"
if [[ ! -f "$PROJECT" ]]; then
  echo "Run this script from apps/client." >&2
  exit 1
fi

python3 - "$PROJECT" "${OPFIN_IOS_BUNDLE_ID:-}" <<'PY'
from pathlib import Path
import re
import sys

path = Path(sys.argv[1])
override = sys.argv[2].strip()
text = path.read_text()
identifiers = set(re.findall(r'PRODUCT_BUNDLE_IDENTIFIER = ([^;]+);', text))
application_ids = {value for value in identifiers if not value.endswith('.RunnerTests')}
if len(application_ids) != 1:
    raise SystemExit('Expected one consistent Runner bundle identifier across build configurations.')
current = next(iter(application_ids))
bundle_id = override or current
if not re.fullmatch(r'[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+', bundle_id):
    raise SystemExit('The iOS bundle identifier must be an explicit valid reverse-domain identifier.')
if override and override != current:
    text = text.replace(f'PRODUCT_BUNDLE_IDENTIFIER = {current};', f'PRODUCT_BUNDLE_IDENTIFIER = {bundle_id};')
    text = text.replace(f'PRODUCT_BUNDLE_IDENTIFIER = {current}.RunnerTests;', f'PRODUCT_BUNDLE_IDENTIFIER = {bundle_id}.RunnerTests;')
    path.write_text(text)
print(f'Configured iOS bundle identifier: {bundle_id}')
print('Use the matching registered Apple App ID, Developer team and distribution profile. No signing identity has been provisioned by this script.')
PY
