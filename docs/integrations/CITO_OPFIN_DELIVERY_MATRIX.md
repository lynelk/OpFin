# Cito–OpFin integration delivery matrix (P0–P2)

Status: **in progress, NOT production accepted**.
Branches: `feat/cito-opfin-p0-p2-integration` in both repositories.
Contract source: Cito `Docs/Api/consumer/external-openapi.json` v2.0.0. Deployed `/releasez` and account entitlements must be checked separately.

## P0: mandatory before monetary operations

| Capability | Source | Required implementation / acceptance |
|---|---|---|
| Contract parity | Cito external OpenAPI | Compare exact schemas and auth/security for OpFin calls; generate contract checks |
| RSA signing | OpFin CitoCapabilityClient, CitoEssentialsLendingClient, CpayV2Adapter, CpayEssentialsClient | Consolidate and cross-test body bytes, query canonicalisation, timestamp/nonce; no auto-retry of side effects |
| Payments | CpayV2Adapter | Preserve stable idempotency, query original reference after unknown outcomes, reject invalid success/finality claims |
| CPay callbacks | WebhookSignatureValidator, CpayWebhookController | Raw-byte HMAC, merchant/reference ownership, replay and delivery dedupe, out-of-order events |
| KYC/credit | CitoCapabilityClient | Consent/purpose checks, provider entitlement and evidence; distinguish failure from negative result |
| OTP/SMS | OpFin existing SMS/OTP gateway | Implement Cito communications contract behind gateway interface, rollout by flags |
| Reconciliation | PaymentReconciliationService | Independently match provider statements, internal operation records and accounting postings |
| Readiness | ProductionIntegrationReadinessService | Replace configuration-only 'ready' with staged evidence-backed states |

## P1: after P0 acceptance

- BaaS read client uses distinct `X-Cito-Api-Key` and `X-Cito-Environment`. Writes require specific scoped operation contracts, governed approvals, deduplication and charge ownership.
- Investment-club/investor cash flows must remain on OpFin's books; third-party balances are settlement evidence, not ownership or NAV.
- Add service request IDs, latency and failure reasons to redacted monitoring, plus commercial usage-cost budgets and alerts.
- Introduce gradual per-capability rollout, feature flags, reconciliation hold queue and rollback instructions.

## P2: conditional extensions

- WhatsApp and USSD require a published entitled external API; do not invent routes from an internal product UI.
- Consumer-neutral application profiles, generic events and workflows belong in Cito, not bespoke OpFin controllers.
- Where provider APIs lack certified credit reporting, safe money custody or additional credit product operations, block feature rather than emulate regulated capability.

## P0 acceptance evidence

- Positive/negative RSA tests, cross-language golden vectors and unauthorised-tenant rejection.
- Synthetic payment tests for duplicate references, changed amount, timeout unknown outcome, pending-to-final state, callback replay and reversal.
- Identity consent revocation and provider outage tests.
- Exact main-SHA CI logs with actual executed jobs and environment-safe DB migrations.
- Provider/operator entitlement, callback secrets, authorised test account and final production approval documented.
- No claim of production readiness solely because a GitHub branch, API schema or test exists.

## Rollout policy

No auto-merge. No production change. No credentials in Git. Do not create new hosting services. Runtime and provider acceptance remain separate from source changes.
