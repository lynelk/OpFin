# OpFin operational manual

Status: Controlled operations and release reference  
Version: 24 September 2026  
Language: English (United Kingdom)  
Audience: operations, support, Finance, Compliance and authorised institutional/programme administrators

## Operating model

OpFin separates Person identity, Financial Spaces, membership/roles, capabilities, entitlements and eligibility. Cito is the preferred third-party gateway and CPay the preferred payment route. OpFin remains responsible for its own product state, expected accounting, reconciliation and audit evidence.

Commercial/service-economics records do not replace the financial ledger. Stolets remains a separate operating product, and specific gnuGrid routing requirements are not overridden by a general direct-provider policy.

## Current release position

Use the [dated delivery evidence](../operations/DELIVERY_EVIDENCE_2026-09-24.md) rather than the earlier 23 September deployment snapshot. For the reviewed `35abeeef57ff8b4a29d6bd5ba2d6575fa9e54c7f` source, the latest API build had six failed tests; Web failed TypeScript checking; worker/scheduler records reported success. This does not establish aligned production.

Essentials has unresolved internal financial-control review findings. Documentation updates do not waive them. GitHub Actions remains disabled by owner instruction; retain equivalent candidate-specific verification without disabling existing build tests or audits.

## Daily controls

Review API live/readiness, worker heartbeat/queue, scheduler cycles, provider callbacks and ambiguous operations, reconciliation/integrity exceptions, failed financial actions, KYC/consent exceptions, complaints/SLA, programme follow-ups, privacy suppression and security alerts.

Never repair a money-state exception by editing a customer status without corresponding provider and accounting evidence. A successful historical deployment is not a fresh health check.

## Financial Space and institutional administration

Confirm Space, role and target record before every administrative action. Personal information is not disclosed merely through group, employer, programme or partner membership.

Institutional progression is profile → required KYB/regulatory evidence → capabilities/products → integration → certification. Technical enablement alone does not activate regulated distribution.

Employer is a Business capability. Current positive-only enrichment permits an approved capped benefit and treats missing/negative information neutrally, but permitted fields must still respect the original minimal-employment-data/privacy boundary. Do not use unrelated disciplinary, attendance or performance data by implication.

## Programmes and partner identities

Use Inclusion & programmes and Programme delivery for programme/sponsor records, versioned instruments, reviewed translations, follow-ups, enrolment/exit, consent exceptions, dedicated partner access and aggregate MEL reporting.

Participant measurements require applicable active consent and enrolment. Protected/programme attributes are not credit-risk inputs. Retain participation-window boundaries after exit. Small cohorts remain suppressed; do not reconstruct them through adjacent filters or exports.

Invite only the intended partner to the intended programme, using a dedicated identity and the configured verification process. Revoke access when authorisation ends. `programme_partner` reporting access is distinct from `partner_api` lending-platform authority.

## Club treasury and statement operations

Follow the [current capability supplement](CURRENT_CAPABILITY_SUPPLEMENT.md) and [treasury specification](../product/INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md).

Validate the account opening balance/date and currency against source evidence. Import mapped CSV into the correct Space/account and preserve original dates/references. Source-hash deduplication is not proof that two differently formatted files contain no overlapping economic records; inspect overlap and match evidence.

Resolve suggested matches, missing book items, external-only/duplicate rows, book-only entries and variances through the supported review decisions. Retain reasons. Do not fabricate a balancing transaction or suppress a difference simply to reach confirmation.

Issue a statement only after the required review state is satisfied. Preserve the issued snapshot and separate currency balances. An internal statement is not external bank verification or complete investment/member-capital accounting.

Current historical-date/baseline failures must be resolved and re-tested before treasury acceptance. Investigate whether balance refresh alters the opening baseline; do not replace valid historical dates with today's date. Detailed import/reconciliation remains a Web workflow in the reviewed source.

## Provider and money-movement operations

Keep adapters disabled until genuine configuration, credentials and legal basis are confirmed. Evidence ingestion proves provenance, not automatic risk eligibility. An ambiguous Cito/provider failure must not silently start the same operation through a direct provider.

Preserve pending state, original request and provider references. Reconcile before retry or explicit route change. Apply idempotency and appropriate locking for economic transitions. Only confirmed, expected events may create the corresponding accounting and receipts.

## Essentials operations and activation hold

Use Essentials operations only within an approved scope while its control findings remain open. The intended capability covers verified service/rental beneficiaries, named third-party lenders, capacity/reservations, quotes, fulfilment and repayment reconciliation. OpFin must not be configured or described as the primary Essentials lender.

Before financial activation, prove:

- expected immutable accounting and reconciliation for each funding, fulfilment, repayment and reversal event;
- exact-Space customer/partner authorisation, including omitted-context requests;
- concurrency-safe repayment reservation and prevention of overcollection;
- governed deletion/closure with pending, active or overdue advances;
- pending lender funding/reversal exposure retained in the overall profile;
- approved, active and funded lender mandates usable through the actual lifecycle.

These are internal acceptance criteria. Lender/biller contracts, Cito/CPay capability certification, rental verification, approved terms and live recovery exercises are additional external/operational gates. Do not say that only credentials remain. All gnuGrid services for this capability remain behind Cito.

## Commercial evidence and exports

Commercial performance covers attribution, governed costs, funnel/repeat use, overdue/NPL outcomes, recorded revenue and contribution. Unknown remains unknown. Principal, insurance premium and investment capital are not platform revenue. Programme graduation is analytics, not credit approval or pricing.

Programme CSV/XLSX/ZIP packs preserve aggregate privacy and causality notices. Financial/provider reports preserve source references, incomplete fields and reconciliation state. Treasury CSV must remain safe to open and must not imply unsupported foreign-exchange conversion.

## Incidents, support and accessibility

Preserve restricted evidence and references, stop duplicate execution, communicate accurate pending/error status, reconcile external/internal truth, escalate financial-integrity/privacy incidents and record remediation. Do not put tokens, identity documents or full customer bank details in public issues.

Assist customers using supported large text, simple wording, reduced motion and high contrast. Record physical-device evidence for assistive technology. Optional location must remain purpose-specific and must not bypass authorisation before an external provider call. Helpers never request PINs/OTPs or weaken identity assurance.

## Release procedure

Record the candidate, affected paths and results; retain independent financial review where required. Run applicable API/Web/client checks, dependency/security checks, Space isolation, replay/concurrency and provider failure/recovery tests. Verify migrations, exact running sources, API/Web health, fresh worker/scheduler heartbeats and applicable reconciliation after deployment.

Use only the approved existing infrastructure. Do not create services, databases, volumes, environments, buckets or replicas or use Railway Agent. Do not remove an existing test/audit to update production. Where a build fails, retain the previous evidence and state the blocked service explicitly.

The [concept and plan comparison](../product/CONCEPT_AND_PLAN_COMPARISON.md) separates achieved source work, internal defects, acceptance gaps and external activation. Revisit it after each accepted release rather than marking the entire plan complete from a merge.
