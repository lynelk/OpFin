# Production deployment and cutover plan

Updated: 18 September 2026

This plan covers deployment of the current `lynelk/OpFin` monorepo. Historical references to separate OpFin-BE/OpFin-FE repositories are superseded.

## Strategy

Use a controlled release with:

1. exact commit passing CI/security/deployment contract;
2. backup/migration readiness;
3. production deployment of API, worker, scheduler and web from the same approved source state;
4. health/readiness verification;
5. migration/queue/scheduler checks;
6. provider/configuration verification;
7. financial integrity/reconciliation checks;
8. customer/support/regulatory smoke tests;
9. rollback if financial or identity state is unsafe.

## Pre-deployment

Confirm:

- database backup/restore path;
- private persistent KYC storage;
- migration review;
- production secrets/provider credentials;
- legal/UMRA/lender identity configuration;
- complaint contacts;
- credit-reference reporting configuration/certification;
- verified affordability provider;
- CPay credentials/callback health;
- WhatsApp/USSD provisioning if in scope;
- feature/capability gates for anything not live.

## Deploy sequence

Recommended service order:

1. API/pre-deploy migrations;
2. worker;
3. scheduler;
4. web;
5. verify all services run the intended commit/configuration.

Do not deploy a web/customer surface that depends on an API contract that has not reached production.

## Post-deploy verification

At minimum:

- `/api/health/live` and `/api/health/ready`;
- login/OTP/PIN with authorised test user;
- profile/KYC status;
- credit profile;
- admin Compliance Centre;
- scheduler/worker heartbeat;
- CPay/provider callback path where safe;
- receipt/reporting job configuration;
- `opfin:umra-credit-controls` execution/observability;
- regulator-report generation using non-sensitive test/authorised data;
- `opfin:integrity-audit` and reconciliation evidence.

Do not create unauthorised real customer loans/payments merely to test deployment.

## Rollback triggers

Rollback or halt rollout when:

- migrations cannot complete safely;
- API health/readiness fails;
- provider callbacks are lost/misrouted;
- duplicate financial events appear;
- ledger/reconciliation integrity fails;
- KYC/consent/privacy controls fail;
- borrower balances/limits materially disagree with source state;
- regulatory-reporting/receipt side effects create incorrect economic or customer records.

## Rollback principle

Code rollback does not mean deleting financial history. Preserve provider/customer/ledger evidence and reverse/correct through controlled append-only workflows where an economic event already occurred.

## Sign-off

Production change sign-off includes Engineering, Operations, Finance, Compliance, Support and Product for the exact deployed release.
