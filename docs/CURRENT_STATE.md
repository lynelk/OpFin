# OpFin current state

Status: Current product and delivery evidence index  
Reviewed: 21 October 2026  
Language: English (United Kingdom)  
Reviewed source baseline: `c04d72f894aba163e6c8aaae668d8ad35a7af731`

## Product position

OpFin remains an integrated personal financial wellbeing and access ecosystem, implemented as a financial operating platform with embedded financial services. The person comes first. Financial Spaces connect households, savings groups, investment clubs and organisations without replacing the individual's identity or exposing their private financial life.

Read the [concept note](product/OPFIN_CONCEPT_NOTE.md) for the continuing vision and the [26 September evolution review](product/PRODUCT_EVOLUTION_2026-09-26.md) for changes, evidence and recommendations. Lending and Essentials are capabilities within OpFin, not its definition.

## Observed source and deployment position (21 October 2026)

The GitHub combined status for `main` commit `c04d72f894aba163e6c8aaae668d8ad35a7af731` reports **successful API, Web, worker and scheduler deployment contexts**. This identifies recorded deployment context status, not newly executed live smoke, financial settlement, backend/mobile acceptance or proof every instance runs the same version. Historical deployment failures remain in dated evidence.

## Verification and historical evidence

September's detailed PR/API/Web test results, the earlier six API failures, and the historical GitHub Actions-disabled note are **time-bound records**, not the latest all-product status. Do not transfer those tests to the current commit. GitHub Actions CI is currently configured and active; its gates must be checked on the exact release candidate.

Current external/operational evidence still needs separate scrutiny: original institution accounts, issuer authenticity, legal lender/custody arrangements, institutional impairment-model review, actual offsite restore and security-owner closure. See [21 October alignment/evidence register](operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md), [17 October six-issue review](operations/ISSUES_17_103_128_136_176_190_STATUS.md) and [institutional finance decision](finance/INSTITUTIONAL_RECONCILIATION_AND_SHARED_EXPOSURE_DECISION.md).

## Source-backed capabilities and boundaries

| Area | Current source position | Required distinction |
| --- | --- | --- |
| Personal Financial Life | Today/Home, aspirations, learning, finance/health records and planning | Guidance is not a credit decision or a guaranteed result. |
| Identity and Spaces | One person with purpose-specific roles, consent and isolated Financial Spaces | One group's permissions do not unlock another or a personal history. |
| Lenders and investment | Governed partner/product/mandate configuration, offers and growth-readiness inventory | Configuration is not funded custody, provider activation or legal investor authority. |
| Investment clubs | Treasury plus separate ownership/capital/valuations/distributions, maker/checker approvals and saved-request recovery | Accounting approval is not an external payout or confirmed bank settlement. |
| Essentials | Purpose finance, durable collection requests, pending capacity, original allocation/reversal and servicing | Complete lender release, biller settlement, activation and refund acceptance are separate. |
| Financial Intelligence | Source-led portfolio import/analysis, statement review/appeal and protected reports behind capability gates | Parsing or issuer naming is not external authenticity/certification. |
| SACCO, white label, asset finance | Source-defined membership/product/action intents, brand requests and evidence profiles | Regulated activity, domain control and provider activation require actual proof. |
| Payroll/referrals | Undertaking/case controls and customer-confirmed partner intent | A referral is not an offer; unknown external reservation is not released. |
| Developer/AI | Searchable, source-linked Developer Centre, partial reviewed OpenAPI, read-only agent tools | No generic native AI financial execution. |
| Institutional ECL and shared exposure | Non-production preview and synthetic QA | Required external GL, agreements and qualified acceptance remain deferred/not certified. |
| Security | History scan and Secret Scanning triage | Actual incident determination, rotation, restore and owner sign-off remain separate. |

## Unresolved evidence and implementation

Older PR #113/#118 descriptions apply to their **September** snapshots. PR #118 and PR #126 were subsequently closed **without merging those heads**; later integrated work may use different commits. They are not currently open PRs, and their test counts do not certify `main`.

Keep material open work visible: complete provider-specific drawdown/settlement/release and refund lifecycles; older-record adoption; source-matched bank/issuer/custody reconciliation; institutional finance/legal and impairment evidence; client/device acceptance; full API schema review; and security incident closure. Product-owner-approved non-production deferrals do not authorise real-money shared origination or professional certification. Details are in the [source-to-acceptance register](operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md).

## Continuing rules and next work

Personal remains the default Space. New App customers use phone, OTP, names and a six-digit PIN with progressive verification; a second phone is optional. Financial health, credit assessment, non-score reputation and programme measurement remain distinct.

The selected institution is the lender of record. Affiliated participation uses the governed institution/product/funding lifecycle and does not manufacture legal authority. Product configuration is separate from market/channel availability.

Cito provides the governed external-service boundary where its capability and country permissions are verified. Any provider switch remains explicitly governed, and an ambiguous original payment may not be replayed on another route. Stolets remains a separate SME operating product.

Complete outstanding control integration, current-release mobile/database/recovery evidence and normal personal/group acceptance; validate treasury history; activate contracted services; then complete the retained wellbeing, club-accounting, automation and longer-range scope. Keep the essential App-only Individual/Savings Group requirement in force.

GitHub Actions workflows are currently active. Retain candidate-specific release-gate evidence and independent runtime/financial acceptance; do not weaken checks or create unapproved infrastructure. This is a documentation change, not a deployment or financial activation.

## Maintenance

Use the [documentation hub](README.md), [concept](product/OPFIN_CONCEPT_NOTE.md), [evolution review](product/PRODUCT_EVOLUTION_2026-09-26.md), [lender contract](architecture/LENDER_ORCHESTRATION.md), [manuals](manuals/OPFIN_USER_MANUAL.md) and [capability supplement](manuals/CURRENT_CAPABILITY_SUPPLEMENT.md). The dated [concept comparison](product/CONCEPT_AND_PLAN_COMPARISON.md) and [implementation index](product/CANONICAL_IMPLEMENTATION_STATUS.md) retain their own assessment dates.

Original requirements, later decisions, source observations and acceptance evidence are separate authorities. Update this index from new evidence rather than simply a new merge date.


## Retained 18 October implementation note (historical)

### Club accounting
The current candidate persists encrypted request envelopes for accounting instructions and statement issuance. Recovery verifies the original reference and content hash, scopes requests to user/book/purpose, rechecks current authorisation and allows resume/read after reload or device change. Acknowledgement releases the recovery slot; cancellation is only for a genuinely unsubmitted request. Former-member navigation exposes that member's own retained capital history only. Mobile statement save/share/print is read-only; opening a system dialogue does not prove delivery or printing.

### Essentials collection and servicing
The current candidate persists collection identity before provider submission and retains provider observations before applying accounting. Uncertain outcomes are reconciled using the original request. A durable one-in-flight constraint reserves capacity, while reserved financing is kept distinct from due debt and production/legacy copies are not double-counted. Repayment allocation/reversal preserves the original policy and append-only balanced servicing evidence.

### Acceptance boundary
These are code candidates, not blanket release acceptance. Targeted transport/policy/arithmetic/syntax checks do not replace complete Laravel/database/migration/concurrency suites, Next.js build, Flutter analysis/tests, Android/iOS release builds, real browser/device/accessibility checks and independent financial review. Essentials lender funding/drawdown, biller settlement, lender release, activation/opening accounting, full mandate compatibility, older-record adoption and post-service-reversal customer refunds remain incomplete. The separate credential-log incident is unresolved. No provider activation, live payment, deployment or financial launch is asserted.

## Current documentation authority

Read the [21 October source and release register](operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md), [API integrator guide](../apps/api/docs/api/INTEGRATOR_GUIDE.md), [role-based training](manuals/ROLE_BASED_TRAINING_PATHS.md) and [publication register](PUBLICATION_REGISTER.md). These supersede the use of September-only evidence as an undated current status. They do not overwrite the historical records.

## 21 October post-baseline implementation update

The reviewed `c04d72f...` source baseline above is historical, **not the latest merge**. Subsequent reviewed merges on `main` include ESLint 10.13.0 ([PR #202](https://github.com/lynelk/OpFin/pull/202)), the default-disabled consent-checked Stolets **shadow-only** Business Passport adapter and administrator screen ([PR #201](https://github.com/lynelk/OpFin/pull/201)), and a patched API asset lockfile for high-severity `follow-redirects` advisories ([PR #208](https://github.com/lynelk/OpFin/pull/208), merge `a5ede03fd64b1f7c61d35be94e6c2d293209284c`). The security-fix candidate passed complete monorepo API/Web/Android/iOS, security, publication and build checks before merge.

Railway production health, exact live deployment SHA, retained legal/customer consents, lender authority and settlements require **post-merge** verification; no code merge is a licence, a funded mandate, a provider contract or live third-party acceptance. The Stolets counterpart remains an independent optional service ([Stolets PR #226](https://github.com/lynelk/Stolets/pull/226)); do not presume it is in production.
