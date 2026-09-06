#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
# Refresh only local framework caches. Never flush the shared runtime cache.
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec frankenphp run --config /Caddyfile --adapter caddyfile
