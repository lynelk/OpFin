#!/usr/bin/env bash
set -euo pipefail

BUNDLE_ID="${OPFIN_IOS_BUNDLE_ID:-co.opfin.app}"
PROJECT="ios/Runner.xcodeproj/project.pbxproj"

if [[ ! -f "$PROJECT" ]]; then
  echo "Run this script from opfin-frontend." >&2
  exit 1
fi

python3 - "$PROJECT" "$BUNDLE_ID" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
bundle_id = sys.argv[2]
text = path.read_text()
text = text.replace('PRODUCT_BUNDLE_IDENTIFIER = org.rotaryo.opfin;', f'PRODUCT_BUNDLE_IDENTIFIER = {bundle_id};')
text = text.replace('PRODUCT_BUNDLE_IDENTIFIER = org.rotaryo.opfin.RunnerTests;', f'PRODUCT_BUNDLE_IDENTIFIER = {bundle_id}.RunnerTests;')
path.write_text(text)
PY

if grep -q 'org.rotaryo.opfin' "$PROJECT"; then
  echo "Legacy bundle identifier is still present." >&2
  exit 1
fi

echo "Configured iOS bundle identifier: $BUNDLE_ID"
echo "Next: open ios/Runner.xcworkspace in Xcode, select your Apple Developer team, and archive with Xcode 26 or later."
