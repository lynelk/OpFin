#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
# Run only on the API service, after backup and exact-revision validation.
# Never seed production or run migrate:fresh / migrate:refresh.
php artisan config:clear
php artisan migrate --force --no-interaction

# Production document evidence must survive redeploys and be shared with workers.
# Fail the release before traffic moves if either private object-storage disk cannot
# complete a write/read/delete round trip. The command redacts provider details.
php artisan opfin:storage-check
