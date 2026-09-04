#!/bin/sh
set -eu

forbidden_files="
app/Services/MtnMomoService.php
app/Services/AirtelCollectionService.php
app/Services/AirtelDisbursementService.php
app/Services/CitotechPaymentService.php
app/Services/MobileMoney/Adapters/MtnMobileMoneyAdapter.php
app/Services/MobileMoney/Adapters/AirtelMoneyAdapter.php
"

for file in $forbidden_files; do
    if [ -e "$file" ]; then
        echo "Forbidden direct payment implementation exists: $file" >&2
        exit 1
    fi
done

for symbol in \
    MtnMomoService \
    AirtelDisbursementService \
    AirtelCollectionService \
    CitotechPaymentService \
    MtnMobileMoneyAdapter \
    AirtelMoneyAdapter
do
    if grep -R -n -F -- "$symbol" app config routes; then
        echo "Direct provider payment reference detected: $symbol" >&2
        echo "OpFin production money movement must route through CPay." >&2
        exit 1
    fi
done

echo "CPay-only production money-movement boundary verified."
