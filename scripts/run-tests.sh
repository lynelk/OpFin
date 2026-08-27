#!/bin/sh
set -eu

# Keep automated tests hermetic even when the host exports production runtime
# variables (for example Railway's DB_CONNECTION=pgsql). Test execution must
# never depend on, connect to, or mutate the production database.
APP_ENV=testing \
APP_DEBUG=false \
DB_CONNECTION=sqlite \
DB_DATABASE=':memory:' \
CACHE_STORE=array \
QUEUE_CONNECTION=sync \
SESSION_DRIVER=array \
MAIL_MAILER=array \
PULSE_ENABLED=false \
TELESCOPE_ENABLED=false \
php artisan test "$@"
