#!/usr/bin/env sh
# Builds the versioned OpFin brand distribution package (issue #103) from committed sources only.
set -eu
cd "$(dirname "$0")/.."
version=$(node -p "require('./brand/opfin.tokens.json').version")
name="opfin-brand-system-${version}"
mkdir -p dist/brand
git archive --format=zip --prefix="${name}/" -o "dist/brand/${name}.zip" HEAD \
  brand \
  apps/web/public/brand/InterVariable.woff2 \
  apps/web/public/brand/INTER-LICENSE.txt \
  apps/client/assets/brand/InterVariable.ttf \
  apps/client/assets/brand/INTER-LICENSE.txt
echo "dist/brand/${name}.zip"
