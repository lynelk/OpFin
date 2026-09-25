# Governed identity evidence reuse and provider continuity

Status: Implemented branch contract; validation and activation evidence recorded separately  
Date: 25 September 2026  
Language: English (United Kingdom)

## Owner direction

CPay remains OpFin's primary and preferred money route. OpFin must retain useful internal functionality during a CPay, Cito or other provider outage. A previously completed NIN validation should be retained as attributable evidence and checked before a repeated external enquiry, with revalidation at an explicitly approved interval.

This does not authorise a copy of a government population register, indiscriminate sharing between customers, fabrication of a provider result, or re-sending an ambiguous payment through another provider. gnuGrid continues through Cito. A NIRA example does not establish a supplied or certified direct NIRA API contract.

## Implemented NIN path

`CitoCapabilityClient::identityCheck(..., 'NIN')` checks `VerifiedIdentityEvidenceService` first. A reusable receipt avoids the external NIN call. On a miss, the existing signed Cito request runs and an attributable PASS or FAIL can be retained. A per-subject cache lock and a second lookup avoid simultaneous redundant enquiries while not locking financial rows.

The receipt is associated with the authenticated user, exact NIN/name/date-of-birth context, country, purpose, provider endpoint/merchant, sandbox/live and application environment, policy version and current explicit reuse-consent record. A change to that context produces a cache miss. Another user's verification is never sufficient to verify the requesting user.

The persisted lookup key is a keyed HMAC derived from the application key, not a raw NIN or an enumerable unkeyed hash. Provider references and minimal result evidence are encrypted. Raw identity responses, names, NINs, access tokens and provider payloads are not copied into this optional cache. Audit metadata uses the receipt UUID, purpose/status, source and dates rather than raw personal evidence.

## Freshness is not renewed by reading

Receipts retain their original observation time, revalidation-due time, expiry, retention limit and provider reference. Reading or replaying an existing result never renews that age. A newer definitive FAIL supersedes an earlier PASS; the lookup does not search backwards for a convenient success. A failed revalidation expires matching active KYC assurance and sends it to review rather than allowing that assurance to stay silently current.

A cache hit returns `evidenceSource`, `evidenceReference`, `verifiedAt`, `expiresAt` and `revalidationDue` alongside the minimal provider result. This is NIN evidence only. It does not complete biometric/liveness, phone ownership, sanctions, consent, affordability or an entire KYC decision.

Expired, revoked, future-dated or wrongly scoped evidence is not reusable. An outage does not extend expiry or convert a request to PASS. Unrelated record keeping, dashboards and financial history must remain available; a newly regulated action may still need fresh evidence and must expose an honest pending/unavailable state.

## Policy and configuration

Production reuse is disabled until the following genuine policy values are supplied:

| Variable | Meaning |
| --- | --- |
| `IDENTITY_EVIDENCE_REUSE_ENABLED` | Explicitly enable this evidence-reuse capability |
| `IDENTITY_EVIDENCE_POLICY_VERSION` | Version identifying the approved policy |
| `IDENTITY_EVIDENCE_APPROVAL_REFERENCE` | Actual accountable approval/provider-storage evidence reference |
| `IDENTITY_EVIDENCE_MAX_AGE_SECONDS` | Maximum approved age for reusing a NIN observation |
| `IDENTITY_EVIDENCE_REFRESH_AFTER_SECONDS` | Earlier revalidation interval, greater than zero and less than maximum age |
| `IDENTITY_EVIDENCE_RETENTION_SECONDS` | Approved optional-cache retention, not less than the reuse age |

Defaults are false/empty/zero. Test fixtures use short synthetic intervals; those are not production approvals. No statutory 30-day, 90-day or annual NIN-cache period is asserted by this implementation.

Reuse also requires an active consent record with purpose `identity_evidence_reuse`, independently of any broader credit-processing consent. Revoking it prevents reuse immediately. Regranting consent does not reactivate receipts attached to a revoked consent record. The optional cache is purged on account deletion and after its retention limit; underlying regulatory KYC records follow their separate authorised retention process.

The existing consent API can create/revoke purpose-specific records. This change does not yet introduce a dedicated customer preference screen or a public NIN lookup endpoint. Approval references cannot manufacture a provider licence or data-retention right.

## Scheduled revalidation

The existing scheduler registers `identity:evidence-maintain --refresh --limit=50` hourly, with overlap prevention and one-server scheduling. No additional Railway service or database is required.

The command purges expired optional receipts and, only under the active approved policy and provider configuration, considers due latest receipts with active subject consent. It limits the batch, processes each subject once per run and applies per-receipt cooldown after either success or failure. Errors produce an aggregate deferred count, not a secret-bearing raw exception message. A failed external refresh does not renew the cached receipt.

Manual operations can use `php artisan identity:evidence-maintain --limit=50` for retention maintenance or the explicit `--refresh` option under an approved policy. Commands do not complete a customer's full KYC review or move money.

## Boundaries still requiring evidence

This first implementation integrates the existing **Cito NIN capability**. It does not claim that every legacy NIN endpoint or a separately configured direct biometric/provider bundle already shares the cache. Direct NIRA or another source needs a genuine provider adapter, source attribution and the same policy/retention/isolation controls before reuse is allowed. The existing outer KYC workflow may still require other independent provider capabilities.

Likewise, this is not money failover. CPay money instructions require canonical idempotent intent, provider finality and reconciliation. A secondary provider may execute only a demonstrably unsubmitted or conclusively failed instruction under the approved routing policy, not an ambiguous in-flight payment.

## Acceptance

Tests cover fresh reuse with no HTTP request, original-age preservation, expiry, changed identity details, consent withdrawal/regrant, sandbox/live separation, another-subject denial, unattributed-result rejection, encrypted/minimised persistence, explicit invalidation, retention deletion, soft account closure and definitive NIN failure withdrawal of KYC assurance.

Record the exact candidate and actual test/review results before merge. Code and a test definition are not proof that the suite or production activation passed. Full launch also requires reviewed privacy wording, lawful/provider-permitted retention, original source reliability, security controls and operational acceptance.
