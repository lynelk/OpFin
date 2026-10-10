# Cito External API v2.0 integration: P0–P2 delivery and acceptance register

Date: 10 October 2026
Scope: OpFin backend integration branch `feat/cito-opfin-p0-p2-integration`; Cito consumer-neutral contract in `Docs/Api/consumer/external-openapi.json` (v2.0). Release: **DRAFT, NOT AUTHORISED FOR PRODUCTION**.

## A. Existing-system architecture and gap assessment

OpFin's financial core already contains durable `MobileMoneyTransaction` intents, fingerprinted idempotency, financial ledger postings, provider outcome state machine, replay-resistant CPay HMAC callbacks and provider-statement reconciliation. Existing `FinancialSpaceActionService`, `ClubInstructions`, `PayrollDeductionService` and `SubscriptionBillingService` preserve independent product ownership. Cito/CPay remains a communications, identity, credit, payments and external billing gateway; it does not own OpFin loan interest, NAV, account positions, payroll mandates or fee revenue recognition.

Observed gaps addressed in this branch:
- Duplicate signed RSA implementations and HTTP 202 ambiguity.
- Identity/credit consent boundary validation and Cito OTP/SMS adapters without full application flow.
- Financial-space replay with changed amount/owner; approval racing with external payment submission.
- Club settlement evidence being mistakenly treated as book allocation.
- Unapproved BaaS write operations and missing service-account budget/account controls.
- Uncontracted general WhatsApp/USSD being treated as if Cito external routes already existed.
- SMS accepted-for-processing being recorded as delivery confirmed.
- Absent explicit platform operations/cost view and custody-mandate records.

## B. Endpoint-to-workflow map

| OpFin consumer workflow | Authoritative Cito v2 endpoint | Implementation boundary |
|---|---|---|
| Signed capabilities and entitlements | GET `/api/v2/capabilities`, GET `/api/v2/identity/capabilities` | `CitoCapabilityDiscoveryClient`; the result is not proof of production release |
| SMS OTP challenge | POST `/api/v2/communication/otp/challenges` | `CitoOtpAuthService`; durable challenge/idempotency, credential signing and single-use token |
| OTP verify | POST `/api/v2/communication/otp/challenges/{challengeId}/verify` | Consumer-bound verification, scoped purpose, max attempts and expiry |
| WhatsApp OTP only | Same OTP challenge, `channels: ['WHATSAPP']` and configured approved `template` | Separate `otp_whatsapp` feature/acceptance flags; never imply general messaging |
| Transactional SMS | POST `/api/v2/communication/messages` | `SmsService`, `SendSms`, stable message key; `Submitted` is not `Sent` |
| SMS delivery state | GET `/api/v2/communication/messages/{reference}` | `CitoCommunicationsClient.messageStatus`; must reconcile delivery evidence |
| NIN, KYC, credit scores and reports | Existing Cito `/api/v2/identity` and `/api/v2/credit` published contracts | Consent-bound `CitoCapabilityClient`, OpFin KYC and scoring services |
| CPay collections and disbursements | POST `/api/v2/native/payments/collect`, counterpart payout/status routes | `MobileMoneyService`, `CpayV2Adapter`; accepted != settled |
| CPay signed callbacks | Cito `callback-v1` HMAC contract | `CpayWebhookController`, `WebhookSignatureValidator`, nonce and event dedupe |
| Payment statements | Existing CPay provider statements/OpFin reconciler | `PaymentReconciliationService`; linked space actions remain unallocated |
| Billing/BaaS quotes and read-only | `/api/v2/native/billing/baas/pricing/quotes`, invoices, quotas, entitlements, customers | `CitoBillingClient` using separately scoped `X-Cito-Api-Key` |
| Governed BaaS charges, usage, subscriptions | `/api/v2/native/billing/baas/charges`, `/usage/events`, `/subscriptions` | `CitoBaasGovernanceService`: maker-checker, journal, daily budget, account scope |
| General WhatsApp messaging and USSD | **No verified general external contract** | Intentionally NOT enabled in Cito adapter; existing distinct OpFin channels remain separate |

## C. OpFin operational APIs

All administrative endpoints below require `auth:sanctum` plus server-side role verification:
- POST `/api/admin/cito-baas/intents`: platform administrator creates a charge/usage/subscription draft.
- POST `/api/admin/cito-baas/intents/{intent}/approve`: a different platform administrator approves and submits once.
- GET `/api/admin/cito-baas/intents/{intent}`: redacted provider-operation state.
- GET `/api/admin/cito-operations/snapshot`: platform admin/operations view of recorded costs, pending intents and release-state flags.
- POST `/api/admin/financial-spaces/{space}/payout-mandates`: evidence record proposed by platform administrator.
- POST `/api/admin/financial-spaces/{space}/payout-mandates/{mandate}/approve`: independent platform-administrator approval.

Existing financial-space actions remain the source of funds movement requests, not direct Cito BaaS charges. Existing club instructions remain the source of accounting entries and NAV. Existing employer payroll deduction mandates and reconciliation remain independent from mobile-money gateway admission.

## D. Migration and rollback

Schema additions (non-destructive, reviewed in sandbox before production):
1. `2026_10_10_000100_create_cito_otp_challenges_table.php`
2. `2026_10_10_000200_create_cito_baas_operation_intents.php`
3. `2026_10_10_000300_add_financial_space_settlement_evidence.php`
4. `2026_10_10_000400_create_cito_baas_daily_write_limits.php`
5. `2026_10_10_000500_create_financial_space_payout_mandates.php`

Default all Cito capability flags, P2 WhatsApp OTP, BaaS writes, and Financial Space payouts to disabled. Apply schema migrations in a separately approved staged run. Keep historical money intents, provider evidence, consent records and challenge references during rollback. Rollback should disable routing first and only then revert non-data-bearing code; NEVER discard unresolved financial journal entries or replay unknown operations.

## E. Financial safety / reconciliation and incident response

- All side effects use a persisted canonical operation reference; never silently retry on timeout, HTTP 429/5xx, or crash.
- HTTP 202 is only processing admission. Reconcile CPay final status independently and then compare provider statement, settlement/bank statements and OpFin accounting entries.
- Duplicate signed callbacks are rejected by nonce replay prevention; a fresh delivery nonce with an already-processed event is idempotent. Mismatched references, amounts or currencies go to an exception queue.
- A statement-matched financial-space action receives `statement_matched_unallocated` status; club book posting requires a separate approved accounting instruction. A reversal requires a book correction.
- If BaaS cannot return authoritative finality, leave state `submitted_unconfirmed` or `submission_unknown`; do not mark revenue earned or customer balance adjusted.
- An approved mandate binds a financial space, currency, environment, custodian agreement, segregated settlement account, independent reviewers, evidence hash and expiry. **These records alone do not certify the provider's custody or regulatory authorisation.**
- On incident: disable affected feature flag without resubmitting unknown effects; preserve references, collect redacted event traces and escalate to named treasury/operations/compliance owners. Reconcile before any corrective posting.
- `CitoOperationsSnapshotService` exposes *recorded* service costs and unpriced events, never invented tariff estimates or claim of provider reconciliation.

## F. Security and compliance

No API/private keys in mobile applications or repository. Cito merchant RSA and BaaS service-account keys are independently provisioned and environment-scoped. Enforce Cito entitlement, independent production acceptance, opfin role/tenant/financial-space ownership and customer consent per purpose. Sanitize audit events and forbid arbitrary JSON fields in BaaS writes. Credit decisioning and loan pricing remain internal. Formal UMRA, Bank of Uganda/payment partner, personal data/CRB and (where applicable) CMA review remain external human-controlled acceptance gates.

## G. Current acceptance evidence and outstanding dependencies

Implemented locally in source: signing, payments, Cito OTP/SMS, optional WhatsApp OTP, credit report/score consent, BaaS reads and governed writes, financial-space replay protection, provider-statement settlement evidence, custody-mandate workflow, daily BaaS write budget, operations snapshot, extensive negative contract tests. See local CI log for the precise test result.

Still NOT proven: deployed Cito `/releasez` revision and production merchant entitlement; live mobile money/bank statement matching; CRB identity/credit provider test account acceptance; investor custody account segregation and legal approval; employer payroll deduction mandates with a certified route; BaaS rate cards, applicable taxes, invoices and billing-account ownership; zero-downtime production migration/rollout evidence; authorised release stakeholders' signatures.

**Completion rule:** code tests passing is necessary but not sufficient. Mark production complete only after successful contract/UAT, provider sandbox and production certification, real statement reconciliation, regulatory/security approval and separately authorised deployment.
