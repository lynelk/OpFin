#!/bin/sh
set -eu

# Railway/Railpack installs Node dependencies before invoking this custom
# build step. Re-running `npm ci` here fights Railpack's node_modules layer
# and can fail with EBUSY on Vite's cache directory. Use the prepared build
# dependencies, while keeping PHP verification hermetic and production-safe.
composer install --no-interaction --prefer-dist --no-progress
cp .env.example .env
sh scripts/enforce-cpay-only.sh
sh scripts/run-tests.sh
composer audit
npm run build
