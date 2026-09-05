#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
php artisan config:cache
php artisan deployment:wait-for-schema --timeout=300
# One scheduler replica; distributed schedule locks use the shared database cache.
exec php artisan schedule:work --no-interaction
