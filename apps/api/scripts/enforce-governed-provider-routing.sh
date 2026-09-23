#!/bin/sh
set -eu

# Enforce the governed money-movement provider boundary. Historical direct-provider services
# that bypassed adapter certification/finality controls remain forbidden.
forbidden_files="
app/Services/MtnMomoService.php
app/Services/AirtelCollectionService.php
app/Services/AirtelDisbursementService.php
app/Services/CitotechPaymentService.php
"

for file in $forbidden_files; do
    if [ -e "$file" ]; then
        echo "Forbidden legacy direct payment implementation exists: $file" >&2
        exit 1
    fi
done

for symbol in     MtnMomoService     AirtelDisbursementService     AirtelCollectionService     CitotechPaymentService
do
    if grep -R -n -F -- "$symbol" app config routes; then
        echo "Legacy provider implementation reference detected: $symbol" >&2
        exit 1
    fi
done

manager="app/Services/MobileMoney/MobileMoneyProviderManager.php"
production="app/Support/ProductionConfiguration.php"
services="config/services.php"

grep -q "configuredDirectProvider" "$manager" || {
    echo "Provider manager must expose the governed direct-provider adapter boundary." >&2
    exit 1
}

grep -q "MobileMoneyProviderInterface" "$manager" || {
    echo "Direct payment adapters must implement MobileMoneyProviderInterface." >&2
    exit 1
}

grep -q "production_certified" "$manager" || {
    echo "Provider manager must enforce production certification for direct adapters." >&2
    exit 1
}

grep -q "mobile_money_provider_certified" "$production" || {
    echo "Production configuration must fail closed for uncertified money-movement providers." >&2
    exit 1
}

grep -Eq "'default_provider'[[:space:]]*=>[[:space:]]*env\('MOBILE_MONEY_PROVIDER',[[:space:]]*'cpay'\)" "$services" || {
    echo "CPay must remain the default governed money-movement route." >&2
    exit 1
}

awk '
    /'\''mobile_money'\''[[:space:]]*=>[[:space:]]*\[/ { in_mobile_money=1; next }
    in_mobile_money && /'\''providers'\''[[:space:]]*=>[[:space:]]*\[/ { in_providers=1; next }
    in_providers && /'\''cpay'\''[[:space:]]*=>[[:space:]]*\[/ { in_cpay=1; next }
    in_cpay && /'\''production_certified'\''[[:space:]]*=>[[:space:]]*true/ { found=1; exit }
    in_cpay && /^[[:space:]]*\],/ { exit }
    END { exit(found ? 0 : 1) }
' "$services" || {
    echo "CPay must remain a certified production payment route inside mobile_money.providers." >&2
    exit 1
}

grep -q "MTN_MOBILE_MONEY_PRODUCTION_CERTIFIED" "$services" || {
    echo "MTN direct-provider activation must remain explicit and certification-gated." >&2
    exit 1
}

grep -q "AIRTEL_MOBILE_MONEY_PRODUCTION_CERTIFIED" "$services" || {
    echo "Airtel direct-provider activation must remain explicit and certification-gated." >&2
    exit 1
}

echo "Preferred CPay route plus certified direct-provider fallback boundary verified."
