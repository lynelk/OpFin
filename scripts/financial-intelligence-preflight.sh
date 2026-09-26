#!/usr/bin/env bash
# Read-only prerequisite check. Does not install packages, alter infrastructure, enable CI or deploy.
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
blocked=0
check() {
  local label="$1"; shift
  if "$@" >/dev/null 2>&1; then printf 'AVAILABLE %s\n' "$label"; else printf 'BLOCKED %s\n' "$label"; blocked=1; fi
}
check 'PHP runtime' command -v php
check 'Composer command' command -v composer
check 'Laravel installed dependencies' test -f "$ROOT/apps/api/vendor/autoload.php"
check 'PHPUnit executable' test -x "$ROOT/apps/api/vendor/bin/phpunit"
check 'Pint executable' test -x "$ROOT/apps/api/vendor/bin/pint"
check 'PostgreSQL PDO driver' php -r 'exit(in_array("pgsql", PDO::getAvailableDrivers(), true) ? 0 : 1);'
check 'Node runtime' command -v node
check 'Web installed Next.js' test -f "$ROOT/apps/web/node_modules/next/package.json"
check 'Web locked TypeScript compiler' test -f "$ROOT/apps/web/node_modules/typescript/lib/tsc.js"
check 'Web installed Vitest' test -f "$ROOT/apps/web/node_modules/vitest/package.json"
check 'Flutter runtime' command -v flutter
check 'Full repository API entrypoint' test -f "$ROOT/apps/api/artisan"
if (( blocked )); then
  echo 'BUILD_STATUS=BLOCKED. Prerequisites are missing. Standalone checks are not full build acceptance.'
  exit 2
fi
echo 'PREREQUISITES_PRESENT_ONLY. Run the repository quality, migration, browser and device gates; no build success is implied.'
