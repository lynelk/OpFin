#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
target="${1:-}"
case "$target" in android|ios) ;; *) echo 'Usage: OPFIN_API_BASE_URL=https://host/api bash tool/build_release.sh android|ios' >&2; exit 2 ;; esac
: "${OPFIN_API_BASE_URL:?Set the public HTTPS API base URL, including /api}"
export OPFIN_API_BASE_URL
python3 - <<'PY'
import os
import re
from urllib.parse import urlsplit

value = os.environ['OPFIN_API_BASE_URL']
try:
    u = urlsplit(value)
    host = (u.hostname or '').lower().rstrip('.')
    labels = host.split('.')
    local = any(host == suffix or host.endswith('.' + suffix)
                for suffix in ('localhost', 'local', 'internal', 'test', 'invalid'))
    # A DNS hostname is required. This also excludes all IP literals and legacy
    # numeric/hexadecimal loopback aliases without requiring a DNS lookup.
    valid = (value == value.strip() and u.scheme == 'https' and not local
             and len(labels) >= 2 and re.search('[a-z]', labels[-1])
             and all(re.fullmatch(r'[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?', label) for label in labels)
             and not u.username and not u.password and not u.query and not u.fragment
             and u.path in ('/api', '/api/') and (u.port is None or 1 <= u.port <= 65535))
except ValueError:
    valid = False
if not valid:
    raise SystemExit('Release API URL must use HTTPS, a public DNS hostname and /api, without credentials, query or fragment.')
PY
command -v flutter >/dev/null || { echo 'Flutter must be installed.' >&2; exit 1; }
flutter pub get
flutter analyze
flutter test
args=(--release --no-pub "--dart-define=OPFIN_API_BASE_URL=${OPFIN_API_BASE_URL%/}" --dart-define=OPFIN_APP_STORE_P2P_BORROWING_ENABLED=false)
if [[ "$target" == android ]]; then
    # Existing Gradle configuration must require a real signing key, even in CI.
    CI=false flutter build appbundle "${args[@]}"
else
    # Xcode signing/team/provisioning must already be configured; no unsigned fallback.
    flutter build ipa "${args[@]}"
fi
