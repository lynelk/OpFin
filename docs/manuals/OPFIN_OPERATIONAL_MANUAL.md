# OpFin Operational Manual

Status: Controlled internal operational manual  
Version: 23 September 2026  
Language: English (United Kingdom)  
Audience: operations, support, finance, compliance and authorised institutional/programme administrators

## Operating model

OpFin separates Person identity, Financial Spaces, membership/roles, capabilities, entitlements and product eligibility.

Cito is the preferred third-party integration gateway where configured. CPay is the preferred production money-movement route. OpFin remains responsible for its own customer/product state, ledger, reconciliation and audit boundaries.

Revenue/service-economics events attribute commercial outcomes and do not replace the financial ledger.

## Current deployment evidence

At reviewed `main` commit `1a580e490ccb4cd5cc55f06ea9d09fad6619fd7e`, GitHub commit statuses report successful Railway deployments for API, Web, worker and scheduler.

No GitHub Actions workflow run is associated with that exact head. Therefore operations may state that the source is deployed, but must not state that the exact head passed the full repository release gate.

## Daily controls

Review:

- API live/readiness;
- worker heartbeat and queue state;
- scheduler heartbeat/cycles;
- provider callbacks and ambiguous provider operations;
- reconciliation/integrity exceptions;
- failed financial actions;
- KYC/consent exceptions;
- support/complaint SLAs;
- programme follow-up exceptions;
- privacy suppression in partner reporting;
- security alerts.

Never resolve a financial exception by editing customer-facing status without provider/accounting evidence.

## Financial Space administration

Validate role and Space before any administrative action. Personal Space information must not be disclosed to employers, groups, programmes or partners merely because a person belongs to them.

## Institutional onboarding

Business/SACCO/Fund/Partner progression remains progressive: profile → KYB/regulatory evidence → capabilities/products → integration → certification.

Do not activate regulated distribution merely because source code exists.

## Employer operations

Employer is a capability of a Business Space. Employee Personal Space data remains private unless a specific lawful process authorises sharing.

Verified positive employment behaviour may create a capped benefit under an approved policy. Missing, unavailable or negative employer-behaviour information is neutral under the current enrichment rule.

## Programme operations

Use **Inclusion & programmes** and **Programme delivery** to manage:

- programmes and sponsors;
- instruments/questions;
- reviewed translations;
- follow-up schedules;
- enrolment/exit;
- measurement-consent exceptions;
- programme-partner access;
- aggregate outcome reporting and MEL exports.

Participant programme measurements require active consent and active enrolment. Protected/programme fields remain outside credit-risk decisioning.

Small cohorts remain suppressed under the configured privacy threshold. Do not attempt to reconstruct suppressed results from exports or neighbouring filters.

## Programme-partner identities

Create programme-partner invitations only for the intended linked programme/partner. Activation requires the configured phone-verification process and a dedicated partner identity.

Partner access is programme-scoped and aggregate-only. Revoke access promptly when authorisation ends.

## Provider-adapter operations

Adapters remain disabled until configuration/credentials evidence and legal basis are explicitly satisfied.

Provider evidence uses explicit allow-lists and purpose/consent boundaries. Successful ingestion proves provenance, not underwriting eligibility.

When Cito is primary, an ambiguous timeout/failure must not silently invoke a direct backup provider. Reconcile the original operation first, then switch route explicitly if policy allows.

## Money movement and reconciliation

Provider acknowledgement is not finality. Preserve pending states until authoritative success/failure is known.

Every money instruction first creates a durable local intent. The external provider call occurs only after that intent is committed. If provider submission becomes ambiguous, preserve the canonical reference and reconcile/lookup before any retry. Do not create a fresh payment merely because the original request timed out.

Use idempotency for retryable economic actions. Reconcile provider evidence to OpFin state and ledger records. Never create balancing entries merely to make a report look tidy.

Only provider-statement evidence may set an item to `matched`. Support may annotate an exception but cannot force a match or write-off. A write-off requires: operations/admin request with reason and evidence hash → approval by a different authorised checker → controlled application. The underlying money movement remains a statement/reconciliation exception; write-off does not fabricate provider evidence.

Before financial UAT or live financial activation run `php artisan opfin:financial-readiness` and retain the JSON evidence. Ordinary API liveness/readiness is not financial sign-off.

## Commercial performance

Use **Commercial performance** for governed acquisition attribution, costs, funnel state, repeat usage, overdue/NPL outcomes, recorded revenue and contribution.

Unknown cost/revenue fields stay unknown. Principal, premium and investment capital are not platform revenue.

Programme-to-commercial graduation is analytics only. It never approves credit or changes pricing/limits.

## Exports and reporting

CSV/XLSX/ZIP programme packs are aggregate and retain privacy suppression/causal-attribution notices.

Partner financial/compliance reports must preserve provider/source references, incomplete fields and reconciliation state. Do not convert missing commercial evidence to zero.

## Incident handling

For a provider or financial incident:

1. preserve original references and audit evidence;
2. stop duplicate execution;
3. maintain accurate pending/error state;
4. reconcile provider and internal truth;
5. escalate privacy/financial-integrity incidents;
6. communicate only verified customer-safe status;
7. record remediation and follow-up evidence.

## Accessibility and assisted use

Support staff should guide customers through large text, simple wording, reduced motion and high contrast where available.

Assistive technology and assisted-KYC certification requires physical-device evidence. Do not weaken identity assurance or request customer PINs/OTPs.

## Release operations

A release requires applicable migrations, API/client checks, Space isolation, financial integrity/idempotency, provider retry/recovery, accessibility/mobile-completeness, reconciliation and documentation alignment.

Railway deployment success and GitHub CI success are separate evidence. Both should be recorded where applicable.

Financial-control changes must also have the repository's independent financial-control approval. Do not merge or release them under a manual success assumption merely because the application deploys.

## External activation

Provider credentials/contracts, programme agreements, legal approvals, reviewed translations, app-store publication and physical-device certification are external activation gates. Record them as pending until genuine evidence exists.
