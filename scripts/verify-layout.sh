#!/usr/bin/env sh
set -eu
for path in apps/api/composer.json apps/web/package.json apps/client/pubspec.yaml RELEASE_MANIFEST.json; do
  test -f "$path" || { echo "Missing required monorepo path: $path" >&2; exit 1; }
done
for path in apps/api/.github apps/web/.github apps/client/.github; do
  test ! -d "$path" || { echo "Nested GitHub control directory is not allowed: $path" >&2; exit 1; }
done
if grep -RIn --exclude-dir=.git --exclude='*.md' --exclude='*.example' -E '(CPAY_PRIVATE_KEY|DB_PASSWORD|APP_KEY)=' apps packages infrastructure; then
  echo "A secret-like assignment was committed." >&2
  exit 1
fi
echo "OpFin monorepo layout and secret boundary checks passed."
