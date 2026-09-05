#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
target="${1:-}"
case "$target" in android|ios) ;; *) echo 'Usage: OPFIN_API_BASE_URL=https://host/api bash tool/build_release.sh android|ios' >&2; exit 2 ;; esac
: "${OPFIN_API_BASE_URL:?Set the public HTTPS API base URL, including /api}"
export OPFIN_API_BASE_URL
python3 - <<'PY'
import os
from urllib.parse import urlsplit
u = urlsplit(os.environ['OPFIN_API_BASE_URL'])
if (u.scheme != 'https' or not u.hostname or u.hostname in {'localhost', '127.0.0.1', '::1'}
        or u.hostname.endswith('.internal') or u.username or u.password or u.query or u.fragment
        or u.path.rstrip('/') != '/api'):
    raise SystemExit('Release API URL must be a public HTTPS origin with /api, without credentials, a query, or a fragment.')
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
