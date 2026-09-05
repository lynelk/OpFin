#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
php artisan config:cache
# The retry interval must exceed this timeout; Railway restarts clean recycling.
exec php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=75 --max-time=3600 --memory=128 --no-interaction
