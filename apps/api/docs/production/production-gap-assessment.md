# Production gap assessment

Updated: 18 September 2026

## Current conclusion

The previous May 2026 “foundation/demo system” assessment is superseded. The monorepo now contains production-shaped authentication, KYC/consent, credit profile/affordability, formal offers, payment finality, immutable ledger, reconciliation, complaint operations, e-receipts and regulatory evidence controls.

The main remaining gaps are **external activation, certification and operational proof**.

## Implemented application controls

- phone/OTP/six-digit PIN account journey;
- NIN + ID front/back + photo holding ID KYC;
- explicit credit-processing and credit-reporting consent;
- decomposable CRB/MNO/approved-partner/internal score profile;
- verified affordability gate for automatic approval;
- profile-level credit limit;
- immutable formal offer/disclosures;
- verified-wallet payout/repayment;
- CPay provider-finality boundary;
- idempotent repayment and reconciliation;
- immutable ledger/reversal controls;
- transaction e-receipts;
- support/complaint SLA;
- credit-information exchange register;
- NPL/default-interest controls;
- guarantor confirmation;
- governed rate/term changes;
- admin regulator books/reports;
- App/WhatsApp/USSD borrower-state continuity;
- accessibility/PWD-assisted KYC support;
- CI/security/deployment and documentation-drift gates.

## Remaining external/operational gaps

1. Verify licensed legal entity/trading name/UMRA licence/business address.
2. Configure/certify production identity provider.
3. Configure/certify applicable credit-reference reporting mechanism.
4. Configure verified affordability/income source.
5. Certify production CPay/payment provider paths.
6. Configure official complaints contacts and staffing.
7. Verify production private KYC storage, backups and restore.
8. Provision WhatsApp/USSD where in launch scope.
9. Complete signed-store candidate and declarations.
10. Complete real-device accessibility/PWD UAT and independent security testing.
11. Rehearse incident/provider outage/reconciliation/restore operations.

## Product-gated capabilities

Savings, insurance/protection, investments, SACCO/community capital, asset finance and participatory finance may be architecturally implemented but remain externally gated until their relevant provider/regulatory arrangements are active.

## Cutover rule

No production cutover based on code completion alone. The exact release commit, migrations, production environment, external provider gates and operational evidence must all pass the current readiness checklist.
