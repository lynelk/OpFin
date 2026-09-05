#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
# Run only on the API service, after backup and exact-revision validation.
# Never seed production or run migrate:fresh / migrate:refresh.
php artisan config:clear
php artisan migrate --force --no-interaction
