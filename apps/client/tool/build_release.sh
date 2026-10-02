#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
target="${1:-}"
case "$target" in
    android) channel=play_store ;;
    huawei) channel=huawei_appgallery ;;
    ios) channel=app_store ;;
    *) echo 'Usage: OPFIN_API_BASE_URL=https://host/api bash tool/build_release.sh android|huawei|ios' >&2; exit 2 ;;
esac
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
git diff --exit-code -- pubspec.lock
flutter analyze
flutter test
args=(--release --no-pub "--dart-define=OPFIN_API_BASE_URL=${OPFIN_API_BASE_URL%/}" "--dart-define=OPFIN_DISTRIBUTION_CHANNEL=$channel" --dart-define=OPFIN_APP_STORE_P2P_BORROWING_ENABLED=false)
case "$target" in
    android)
        # Production artifacts require the registered real key, including in CI.
        CI=false flutter build appbundle "${args[@]}"
        ;;
    huawei)
        # Same Flutter/Android application; AppGallery identity and signing must be real.
        CI=false flutter build apk "${args[@]}"
        mkdir -p build/release-delivery/huawei
        cp build/app/outputs/flutter-apk/app-release.apk build/release-delivery/huawei/OpFin-AppGallery.apk
        ;;
    ios)
        # Preserve the registered App ID unless an explicit override is supplied.
        bash tool/prepare_app_store.sh
        # Team, certificate and provisioning are required; no unsigned fallback.
        flutter build ipa "${args[@]}"
        ;;
esac
