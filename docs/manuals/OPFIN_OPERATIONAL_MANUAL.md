# OpFin operational manual

Status: Controlled operations and release reference  
Version: 25 September 2026  
Language: English (United Kingdom)  
Audience: operations, support, Finance, Compliance and authorised institutional/programme administrators

## Operating model

OpFin separates Person identity, Financial Spaces, membership/roles, capabilities, entitlements and eligibility. Cito is the preferred external-service gateway and CPay the preferred payment route. They are not permission to block unrelated internal record keeping, reporting or account access during an outage. OpFin owns product state, expected accounting, reconciliation and audit evidence.

Commercial/service-economics records do not replace the ledger. Stolets remains a separate operating product, and the specific gnuGrid-through-Cito rule is not overridden by a generic fallback policy. Do not send an ambiguous money instruction through a second route.

## Current release evidence

The [24 September record](../operations/DELIVERY_EVIDENCE_2026-09-24.md) preserves the then-failing API and Web build evidence. The treasury baseline/role repair at `a56289bcfbf05759d6afd844d89f7e8a39ae34db` subsequently passed the existing isolated API build procedure with **265 tests and 1,872 assertions**, dependency audit and asset compilation. It deliberately stopped before runtime deployment. Those six earlier failures are therefore resolved in the tested candidate; a later production claim must identify the actual merged/running revision.

The repair preserves the account opening date instead of replacing it during balance refreshes, and includes the implemented partner API role without granting it general administration. It does not complete club investment accounting or the wider Essentials control review.

GitHub Actions remains disabled at the owner's instruction. Equivalent candidate-specific checks remain required. A release tool that reports success without the expected tests is not acceptable evidence; review actual logs and outputs.

## Daily controls

Review API live/readiness, worker heartbeat and queue, scheduler cycles (in `/api/health/ready`, `warming` means a heartbeat has never been seen and `stale` means it is over 12 minutes old; check the service's Railway log, then that every service uses the same cache prefix), provider callbacks and ambiguous requests, accounting/reconciliation exceptions, failed financial actions, KYC/consent exceptions, complaint SLAs, programme follow-ups, privacy suppression and security alerts.

Do not change customer financial status without matching provider and accounting evidence. A historical successful deployment is not a fresh health check. Keep secrets out of diagnostic commands and restrict access to operational logs; any accidental credential output requires incident handling and controlled rotation.

## Space and institutional administration

Confirm Space, role and target record before every administrative action. Group, employer, programme or partner membership does not automatically disclose Personal Space information.

Institutional progression is profile → required KYB/regulatory evidence → capabilities/products → integration → certification. Enabling a capability does not itself activate regulated distribution.

Employer is a Business capability. Approved positive-only enrichment may provide a capped benefit and treats missing/negative information neutrally. Permitted fields must still respect the original minimal-employment-data boundary; do not infer authority to use unrelated disciplinary, attendance or performance records.

## Staff access, float top-ups and SMS records

The legacy back-office on the API origin stays available until the Web back office covers all of its tasks. It now requires a `platform_admin` or `operations` account, and only a `platform_admin` can create or edit user records and institution administrators.

- **New accounts.** Accounts created by staff have no usable password. Ask the account holder to choose **Forgot PIN** and set their own PIN with the one-time code sent to their phone. Never share a PIN, password or code on their behalf.
- **Staff awaiting review.** Former institution administrators are in the `staff_pending_review` role and cannot sign in. Run `php artisan opfin:legacy-roles` to list them, then record each reviewed decision (operations, support, or no staff access) with a platform administrator.
- **Float top-ups.** Record the amount and attach the evidence image. The top-up stays **Pending** until a different staff member checks the evidence and selects **Approve**. Only approval changes the Disbursement balance. You cannot approve a top-up you recorded.
- **SMS records.** One-time codes appear as `******` in stored SMS records. If a customer says a code didn't arrive, ask them to request a new one; staff cannot see or resend a code.
- **Account deletion.** The API-origin `/account/delete` page forwards to the Web deletion page, which applies the regulated deletion checks.

## Programmes and partner identities

Inclusion & programmes and Programme delivery manage programme/sponsor records, versioned instruments, reviewed translations, follow-ups, enrolment/exit, consent exceptions, dedicated partner identities and aggregate MEL reporting.

Participant measurement requires applicable active consent and enrolment. Protected/programme fields are not credit-risk inputs. Retain participation-window boundaries after exit. Small cohorts remain suppressed; do not reconstruct them through adjacent filters or exports.

Invite only the intended partner to the intended programme through dedicated identity verification. Revoke authority when it ends. Programme-partner reporting and Essentials partner-API permissions are not interchangeable.

## Club treasury and statements

Follow the [capability supplement](CURRENT_CAPABILITY_SUPPLEMENT.md), [treasury specification](../product/INVESTMENT_CLUB_TREASURY_AND_STATEMENTS.md) and [current API contract](../../apps/api/docs/api/CURRENT_CAPABILITY_CONTRACTS.md). The 25 September repair supersedes earlier statements that the five tested historical-date workflows remain blocked.

Verify currency, opening balance and opening date against source evidence. **The opening date remains fixed after account creation.** Current-balance recalculation must not move that baseline. Entries and statements before the genuine opening baseline still fail validation. The tested example opens at UGX 1,000,000 on 1 September, receives 250,000 on 5 September and pays 100,000 on 10 September, leaving 1,150,000 without changing the opening date.

The repair does not guess or rewrite an already-corrupted historical baseline. Resolve any existing affected account from original approved records. Never alter correct source dates or remove the date guard merely to make an import pass.

Import mapped CSV into the correct account. Identical-file deduplication is not proof that differently formatted files contain no overlapping transactions. Review suggestions, missing book items, external-only or duplicate rows, book-only items and closing variances with source evidence and reasons.

Confirmation retains accepted differences; it must not fabricate balancing entries. Issued snapshots preserve history and separate currencies. OpFin statements are not bank-issued statements or complete member-capital, NAV/unitisation, distribution and investment-performance accounting. Detailed import/reconciliation remains a Web workflow requiring the appropriate channel acceptance.

## Providers, identity evidence and money movement

Keep providers unactivated until genuine configuration, contracts and certification are supplied. Evidence ingestion proves provenance, not automatic underwriting eligibility. Cached identity evidence, where separately implemented and approved, must preserve source, subject, purpose, verification age and consent; it must not stand in for biometric, phone, sanctions or affordability checks.

Provider acknowledgement is not finality. Preserve pending state and original references, reconcile before retry, and change route only under an explicit safe policy. Apply payload-bound idempotency and appropriate concurrency controls. Confirmed, expected financial events must produce the required accounting and receipts.

## Asset passports, liens and theft reports

The asset registry is off until `OPFIN_ASSET_IDENTIFIER_KEY` is set. Use the review queue (`/api/admin/asset-passports/review-queue`) daily.

- **Review.** A passport reaches the queue when one of its identifiers is already active on another passport or belongs to a reported-stolen asset. Contact the customer for evidence. Never tell them about the other Space. Clear the review only after the other passport has been disposed of or rejected; otherwise reject it, with a reason.
- **Verification.** Verify only with first-hand evidence: a physical inspection, a dealer invoice, an OEM record or a registry extract. You cannot verify an asset you registered. Verification confirms identifiers, not ownership or value.
- **Liens.** Register a lien only against an open financing arrangement in the same Space. Release it after settlement. Releasing before settlement needs a platform administrator and a recorded lender instruction.
- **Theft and recovery.** A theft report blocks new liens; existing liens stay in place. Record recovery only on evidence such as a police or partner confirmation.
- **Device controls.** Remote device controls are not available. Never promise or attempt device locking.

Before quoting asset-backed finance, lenders' staff use the identifier check. It shows only registry state, never the owner or Space.

## Financing Product Factory

Configure financing products only through `/api/admin/financing-factory`. Nothing configured there is a production launch on its own.

- **Templates.** A platform administrator drafts the template from the approved credit or product policy and cites it in `policy_reference`. A different administrator approves it. To change bounds, draft the next version; never ask for an approved template to be edited.
- **Passports.** Draft a Legal Product Passport only from legal's written opinion or the licence record. A different administrator approves it with the licence and evidence references. If a licence lapses or legal withdraws approval, revoke the passport at once: every product relying on it stops matching.
- **Products.** Name the actual lender, funder and contracting principal exactly as in the partner agreement. Disclosures must be the approved customer wording. Approval needs an administrator who neither made nor submitted the product.
- **Islamic products.** Activation needs an approved, current Sharia approval recorded through governance. Staff cannot create or override one. Never describe a product as Sharia-compliant before that approval exists.
- **Changes.** Revise a live product to create the next version. Activating the new version retires the old one; agreements already made keep their own snapshot.

## Platform administrator account and password recovery

- **First administrator.** In the `OpFin` Railway service shell, run `php artisan opfin:admin:bootstrap --email=<email> --phone=<phone> --first-name=<first> --last-name=<last>` and type a one-time password at the hidden prompt (8+ characters with mixed case, a number and a symbol). Never pass a password on the command line, in a variable, in a ticket or in chat. The administrator must choose a new password (12+ characters) at first sign-in.
- **Recovery.** Staff use **Forgot password?** on the Web sign-in page: an emailed code for their work email, or an SMS code for their phone. If both fail, the owner runs `php artisan opfin:admin:reset-password --email=<email>` in the service shell. It sets a one-time password, ends every session for that account and is audited.
- **Email.** Email codes need a mail provider. Set `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` on the `OpFin` and `opfin-worker` services. Codes are sent from the queue, so the worker must be running. Until a provider is set, use the SMS code or the owner command.
- **Default credential removed.** User accounts no longer default to the password "password". Any staff account that still had it was given a random password, signed out and flagged to change its password after recovery.

## Essentials operations and activation hold

The intended capability covers verified bill/rental beneficiaries, named third-party lenders, funding capacity, quotes, purpose-bound fulfilment and repayment servicing. OpFin must not be configured or described as the primary Essentials lender.

Before financial activation, prove expected immutable accounting and canonical reconciliation; exact-Space permissions including omitted context; concurrency-safe collection and overcollection prevention; deletion/closure with open advances; retained pending funding/reversal exposure; and a usable approved capital-mandate lifecycle.

These internal controls are separate from lender/biller agreements, certified Cito/CPay routes, approved terms, beneficiary verification and operational recovery exercises. Treasury test success does not close these findings or make credentials the only remaining work.

## Commercial evidence and exports

Commercial performance includes acquisition attribution, governed costs, funnel/repeat use, portfolio outcomes, recorded revenue and contribution. Unknown is not zero. Principal, premium and investment capital are not platform revenue. Programme graduation is analytics, not loan approval or pricing.

Programme CSV/XLSX/ZIP exports retain aggregate privacy and causality notices. Financial/provider reports retain source references, incomplete fields and reconciliation status. Treasury CSV must be safe to open and must not imply unsupported FX conversion.

## Incident handling, support and accessibility

Preserve restricted evidence, stop duplicate execution, communicate accurate customer-safe state, reconcile external/internal truth and escalate financial-integrity/privacy incidents. Record cause, containment, remediation and verified closure. Never put tokens, credentials, identity documents or full account numbers in public issues.

For a credential exposure, restore a safe command path, restrict affected logs and rotate using an approved procedure. Database password changes must be coordinated with all consumers. Application-key rotation must preserve required encrypted records and include session invalidation and data re-encryption as applicable; do not simply replace the key and make historical data unreadable.

Assist customers through supported larger text, simple wording, reduced motion and high contrast. Record actual device/assistive-technology evidence. Optional location remains purpose-bound and authorised before provider calls. Helpers never request PINs/OTPs or reduce identity assurance.

## Release procedure

Record candidate, changed paths and actual checks. Retain independent review where required. Run applicable API/Web/client tests, dependency/security checks, isolation, replay/concurrency and provider failure/recovery tests. Confirm migrations, actual running source, API/Web health, worker/scheduler freshness and applicable reconciliation after deployment.

Use existing approved infrastructure. Do not create a new service, database, environment, volume, bucket or replica, use Railway Agent, enable Actions or remove a test/audit merely to force a release. Validation-only runs must have an explicit no-deployment barrier and their temporary configuration must be restored.

The [concept comparison](../product/CONCEPT_AND_PLAN_COMPARISON.md) separates original requirements, later decisions, implementation and acceptance. A merge, test count or documentation publication is not a blanket financial-launch certificate.

## Affiliated lending and channel decisions

Use **Admin → Lenders & credit deployment** to configure Core Synergies as an affiliated institution and record its actual authority. Link its internal funding partner to a maker-checker-approved capital mandate. Choose withhold, external-first fallback or explicit affiliated priority; record the reason, per-loan cap and expiry. Only platform admin can set strategy or delegate credit management. Existing repayments continue while origination is withheld.

Record product-specific store restrictions as distribution revisions with source evidence and a partner-facing reason. Keep the product definition intact. A lender's licence does not by itself establish store approval. Follow the [full operating contract](../architecture/LENDER_ORCHESTRATION.md); foreign-country metadata does not activate local rails.

## PR 142 candidate acceptance update (2 October 2026)

Account deletion rejects unresolved obligations immediately and preserves servicing; optional-data deletion retains the account and regulated evidence. Payroll undertaking is case-bound and atomic with reservation request; cancellation/expiry awaits evidenced provider release. Apply `docs/releases/2026-10-02-pr142-release-acceptance.md` to OPF-FEAT-0022, FIN-FOUNDATION, DOC-CONTINUITY and OPF-REL-0001. This entry is implementation/validation work, not production or store acceptance.
