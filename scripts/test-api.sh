#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/../apps/api"
cp -n .env.example .env || true
composer install --no-interaction --prefer-dist
php artisan key:generate
sh scripts/enforce-cpay-only.sh
sh scripts/run-tests.sh
composer audit
