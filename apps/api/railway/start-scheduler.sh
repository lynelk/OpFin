#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
php artisan config:cache
# One scheduler replica; distributed schedule locks use the shared database cache.
exec php artisan schedule:work --no-interaction
