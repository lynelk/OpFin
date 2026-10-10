# Cito v2 and OpFin implementation evidence

Status: IN PROGRESS ON DRAFT BRANCH. NOT APPROVED FOR PRODUCTION.

## Existing audited safeguards

The OpFin canonical monorepo already contains durable MobileMoneyTransaction intent and idempotency fingerprints, submission claims, ambiguous response recovery, locked transitions and separate provider, accounting and statement reconciliation states. Its CPay webhook verifier authenticates raw callback bodies and persists replay nonces. Club instructions have separate maker-checker approvals and explicitly record that accounting approval does not itself move money.

## Changes on the integration branch

- Shared RSA-SHA256 v2 signing primitive, exact signed-body hashing and HTTPS-only Cito transport. CPay and Cito merchant keys remain separate from BaaS service-account keys.
- Payment HTTP 202 is always pending. HTTP 5xx and 429 are ambiguous; they cannot be posted as terminal failures or automatically resubmitted.
- Cito credit report and score requests check customer consent ownership, purpose, grant and revocation.
- Gated Cito SMS and server-side OTP challenges, with durable reference, one-time verification and customer PIN-reset protection; no provider code is stored in OpFin.
- Signed Cito capability discovery and BaaS scoped customer, catalogue, usage, quote and invoice reads. Billing charges, usage writes and subscription writes require separately enabled permissions and gateway scopes.
- CI Docker test image configured with PHP GD/JPEG and modern Node support.
- Unsupported Cito external WhatsApp and USSD operations remain disabled. The current external v2 projection is limited to SMS and OTP for communications.

## Remaining evidence gates before production

1. Execute full tests on the final branch SHA. Record CI job IDs, actual executed steps and test counts.
2. Confirm the Cito deployed /releasez source revision, production developer project, entitlements, RSA public key registration, service-account scopes and callback secret ownership.
3. Complete end-to-end customer journey UAT and provider response verification, especially optional purpose-specific OTP clients and identity/credit entitlement.
4. Reconcile CPay provider statement to OpFin ledger and bank/settlement account with independently approved samples.
5. Test cross-tenant rejection, environment isolation, release rollback, key rotation, network timeouts and delayed or out-of-order callbacks under realistic workloads.
6. Complete investor and investment-club custody and ownership design. Never equate a member-approved club accounting instruction with an authorised cash transfer.
7. Complete employer salary deduction and settlement mandate validation before live deductions.
8. Accept exact Billing/BaaS price schedules, charge ownership, tax codes, maker-checker permissions, invoicing and usage reconciliation; do not merge external service billing with loan interest calculations.
9. Implement production cost alerts, event replay controls and support/incident runbooks.
10. Approve legal and regulatory obligations, including UMRA, applicable Bank of Uganda/payment requirements, personal data, CRB contracts and applicable capital markets rules.
11. Obtain independent review and merge by standard protected-branch controls; no production deployment or new service provisioning has been performed.

## Rollout and rollback

Feature flags are off by default. Cito OTP activation requires clients to submit a purpose appropriate to registration, login or password reset; retain legacy authentication until both client versions and the challenge migration are accepted. For payment changes, disable newly enabled feature flags first and preserve the durable intent, settlement evidence, original references and webhook inbox. Never replay an ambiguous payout when rolling back.

## P2 constraint

Do not infer WhatsApp or USSD external entitlements from internal platform functionality. Add a new versioned external contract and security tests only after Cito publishes and provisions an approved route.
