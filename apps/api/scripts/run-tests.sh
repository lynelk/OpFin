#!/bin/sh
set -eu

# Keep automated tests hermetic even when the host exports production runtime
# variables (for example Railway's DB_CONNECTION=pgsql and
# MOBILE_MONEY_PROVIDER=cpay). In particular, Railway's DB_URL must be cleared:
# Laravel treats a connection URL as authoritative and would otherwise merge it
# into the explicitly selected sqlite test connection. Test execution must never
# depend on, connect to, or mutate the production database or invoke the production money-movement
# adapter unless an individual test explicitly opts into CPay configuration.
APP_ENV=testing \
APP_DEBUG=false \
DB_CONNECTION=sqlite \
DB_URL='' \
DATABASE_URL='' \
DB_DATABASE=':memory:' \
DB_HOST='' \
DB_PORT='' \
DB_USERNAME='' \
DB_PASSWORD=testing \
CACHE_STORE=array \
QUEUE_CONNECTION=sync \
SESSION_DRIVER=array \
MAIL_MAILER=array \
MOBILE_MONEY_PROVIDER=mock \
OPFIN_REQUIRE_FUNDING_POOL_ASSIGNMENT=false \
OPFIN_REQUIRE_REGULATED_CREDIT_DISCLOSURE=false \
OPFIN_EFRIS_REQUIRED=false \
CITO_FINANCIAL_DATA_CERTIFIED=false \
CPAY_ENVIRONMENT=sandbox \
PULSE_ENABLED=false \
TELESCOPE_ENABLED=false \
php artisan test "$@"
