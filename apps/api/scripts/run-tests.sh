#!/bin/sh
set -eu

# Keep automated tests hermetic even when the host exports production runtime
# variables (for example Railway's DB_CONNECTION=pgsql and
# MOBILE_MONEY_PROVIDER=cpay). Test execution must never depend on, connect to,
# or mutate the production database or invoke the production money-movement
# adapter unless an individual test explicitly opts into CPay configuration.
APP_ENV=testing \
APP_DEBUG=false \
DB_CONNECTION=sqlite \
DB_DATABASE=':memory:' \
CACHE_STORE=array \
QUEUE_CONNECTION=sync \
SESSION_DRIVER=array \
MAIL_MAILER=array \
MOBILE_MONEY_PROVIDER=mock \
CPAY_ENVIRONMENT=sandbox \
PULSE_ENABLED=false \
TELESCOPE_ENABLED=false \
php artisan test "$@"
