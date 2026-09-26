#!/bin/sh
set -eu

# Railway/Railpack installs Node dependencies before invoking this custom
# build step. Re-running `npm ci` here fights Railpack's node_modules layer
# and can fail with EBUSY on Vite's cache directory. Use the prepared build
# dependencies, while keeping PHP verification hermetic and production-safe.
composer install --no-interaction --prefer-dist --no-progress
cp .env.example .env
sh scripts/enforce-governed-provider-routing.sh
sh scripts/run-tests.sh
composer audit
# Validate source-linked documentation definitions without claiming that
# every existing domain contract is complete. The strict coverage gate is
# available separately as: php artisan api:catalogue --check --require-complete.
php artisan api:catalogue --check
npm run build
