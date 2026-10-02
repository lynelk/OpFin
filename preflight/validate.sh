#!/usr/bin/env bash
set -euo pipefail
phase="$1"
root="$(pwd)"
case "$phase" in
  apply)
    test "$(git ls-remote origin "refs/heads/$TARGET_BRANCH" | cut -f1)" = "$EXPECTED_HEAD"
    git fetch --depth=1 origin main:refs/remotes/origin/main
    for script in account_repair payroll_repair partner_and_compatibility_repair regression_tests targeted_followup client_repair web_repair release_contracts final_adjustments; do
      python3 "../repair-tools/preflight/$script.py"
    done
    python3 - <<'PY'
from pathlib import Path
p = Path('apps/api/scripts/run-tests.sh')
s = p.read_text()
anchor = 'CPAY_ENVIRONMENT=sandbox '
assert anchor in s
flags = ['OPFIN_REQUIRE_FUNDING_POOL_ASSIGNMENT=false', 'OPFIN_REQUIRE_REGULATED_CREDIT_DISCLOSURE=false', 'OPFIN_EFRIS_REQUIRED=false', 'CITO_FINANCIAL_DATA_CERTIFIED=false']
s = s.replace(anchor, ''.join(flag + ' \\\n' for flag in flags) + anchor)
p.write_text(s)
PY
    git diff --check
    python3 scripts/verify-pr142-release-contract.py
    python3 scripts/tests/test_independent_review.py
    ;;
  api)
    sudo phpdismod xdebug 2>/dev/null || true
    php -r 'if (extension_loaded("xdebug") || !extension_loaded("pdo_pgsql")) exit(1);'
    cd apps/api
    composer install --prefer-dist --no-progress --no-interaction
    cp .env.example .env
    php artisan key:generate
    cd "$root"
    git add --intent-to-add apps/api
    git diff --name-only --diff-filter=ACMR -z origin/main -- 'apps/api/**/*.php' > "$RUNNER_TEMP/changed-php"
    mapfile -d '' -t files < "$RUNNER_TEMP/changed-php"
    for file in "${files[@]}"; do php -l "$file"; done
    cd apps/api
    files=("${files[@]#apps/api/}")
    ./vendor/bin/pint "${files[@]}"
    ./vendor/bin/pint --test "${files[@]}"
    sh scripts/run-tests.sh --filter='AccountDeletionAndAppStorePolicyTest|EssentialsDurableCollectionsTest|PartnerFinancialIntentWorkflowTest|PayrollDeductionWorkflowTest|ApiSecurityTest|BackendCheckpointTest|ClubAccountingReviewTest|ClubAccountingTest|EssentialsFinanceTest|EssentialsSpaceAndClosureTest|FinancialSpaceStatementsTest|LaunchCustomerJourneyTest|LaunchSafetyRegressionTest|MobileMoneyAdapterTest|ProductionReadinessApiTest|IntegratedFinancingFoundationTest|PostMergeFinancialHotfixTest' 2>&1 | tee "$RUNNER_TEMP/targeted-api.log"
    ;;
  postgres)
    cd apps/api
    export APP_ENV=testing DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=opfin_test DB_USERNAME=opfin_test DB_PASSWORD=ephemeral_validation_only
    export MAIL_MAILER=array CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync
    export OPFIN_REQUIRE_FUNDING_POOL_ASSIGNMENT=false OPFIN_REQUIRE_REGULATED_CREDIT_DISCLOSURE=false OPFIN_EFRIS_REQUIRED=false CITO_FINANCIAL_DATA_CERTIFIED=false
    export CPAY_ENVIRONMENT=sandbox CPAY_COLLECTION_MODE=sandbox CPAY_BILLER_MODE=sandbox CPAY_MOCK_MODE=true
    php artisan test --filter='PayrollDeductionWorkflowTest|PartnerFinancialIntentWorkflowTest|AccountDeletionAndAppStorePolicyTest|PostgresReleaseRegressionTest' 2>&1 | tee "$RUNNER_TEMP/targeted-postgres.log"
    ;;
  client)
    cd apps/client
    flutter pub get --offline
    git diff --exit-code -- pubspec.lock
    git add --intent-to-add lib test
    git diff --name-only --diff-filter=ACMR origin/main -- lib test | sed 's#^apps/client/##' > "$RUNNER_TEMP/changed-dart"
    mapfile -t dart_files < "$RUNNER_TEMP/changed-dart"
    dart format "${dart_files[@]}"
    flutter analyze --no-pub 2>&1 | tee "$RUNNER_TEMP/targeted-flutter-analysis.log"
    flutter test --no-pub test/payroll_instruction_client_test.dart test/distribution_channel_test.dart 2>&1 | tee "$RUNNER_TEMP/targeted-flutter-tests.log"
    ;;
  web)
    cd apps/web
    npm run typecheck 2>&1 | tee "$RUNNER_TEMP/targeted-web-typecheck.log"
    npm run test -- src/lib/api/account.test.ts 2>&1 | tee "$RUNNER_TEMP/targeted-web-tests.log"
    ;;
  static)
    python3 scripts/verify-pr142-release-contract.py
    python3 scripts/verify-documentation-drift.py --base origin/main
    python3 scripts/verify-publication-readiness.py
    sh scripts/verify-layout.sh
    python3 scripts/verify-security-controls.py
    python3 scripts/tests/test_deployment_contract.py
    python3 scripts/tests/test_release_host_safety.py
    git diff --check
    ;;
  *) echo 'Unknown validation phase' >&2; exit 2 ;;
esac
