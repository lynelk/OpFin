# OpFin current state

Status: Current product and delivery evidence index  
Reviewed: 26 September 2026  
Language: English (United Kingdom)  
Reviewed source baseline: `b1686989a317619562a8592728a310fbcb8f9113`

## Product position

OpFin remains an integrated personal financial wellbeing and access ecosystem, implemented as a financial operating platform with embedded financial services. The person comes first. Financial Spaces connect households, savings groups, investment clubs and organisations without replacing the individual's identity or exposing their private financial life.

Read the [concept note](product/OPFIN_CONCEPT_NOTE.md) for the continuing vision and the [26 September evolution review](product/PRODUCT_EVOLUTION_2026-09-26.md) for changes, evidence and recommendations. Lending and Essentials are capabilities within OpFin, not its definition.

## Latest observed deployment status

The final connected GitHub status check for `b1686989a317619562a8592728a310fbcb8f9113` reports **success for all four service contexts**: API (`OpFin - OpFin`), Web, worker and scheduler.

The API success was recorded at 22:15:09 UTC on 25 September 2026, with deployment identifier `368657ce-46a7-49d4-a570-00c5bc157315`. It follows the earlier failed attempt at 21:07:15 UTC. Preserve that history, but do not present the earlier failed attempt as the latest deployment result.

These are connected statuses, not newly executed live-health, exact running-version, customer or financial-activation tests. A successful deployment is not a complete release acceptance certificate.

## Recorded verification and its scope

The [25 September lending delivery record](operations/LENDING_DELIVERY_2026-09-25.md) reports 308 tests and 2,076 assertions passing after its timezone correction, plus two existing PHP deprecation notices. It separately records 35 Web tests and passing typecheck/lint/build for the lending candidate.

The later PR #124 merge record reports integrated candidate `ad79b8b4345fefa06c1c7e8ee37fad4d51e26c78` passed 332 API tests and 2,230 assertions, dependency audit, catalogue checks and assets. The earlier Composer timeout remains historical rather than the latest recorded audit outcome.

These are attributed existing results, not application tests rerun by this documentation review. Full mobile release/device acceptance, rollback/recovery evidence and the earlier HTTP smoke mismatch still lack closure evidence in the reviewed material. Current PostgreSQL branch evidence is recorded separately below; it must not be omitted or transferred to main.

The [24 September record](operations/DELIVERY_EVIDENCE_2026-09-24.md), including 257 passing/six failing tests and a Web typecheck failure, retains its original date and baseline. The current README records the treasury-baseline repair and governed NIN reuse as merged enhancements. Historical reports are not live status pages.

## Source-backed achievements and boundaries

| Area | Progress | Boundary |
| --- | --- | --- |
| Canonical platform | Laravel API, Next.js Web and Flutter client; separate worker/scheduler responsibilities | Shared source does not prove complete cross-channel acceptance. |
| Identity and Financial Spaces | Phone-first onboarding, consent, roles/membership and governed fresh NIN receipt reuse | Exact-Space authority must hold in every embedded path. NIN reuse is not complete KYC. |
| Everyday financial life | Money records, assets, obligations, budget/calendar/goals and Compass surfaces | Estimates require data context; recorded settlements are not external payment proof. |
| Credit and lenders | Assessment, offers, servicing and affiliated/independent lender distribution | Actual authority, capital, affordability and accepted financial controls remain necessary. |
| Save, Protect and Grow | Product, position/order/policy and partner-confirmation foundations | Live custody, withdrawal, cover, claims and redemptions are individually gated. |
| Inclusion programmes | Enrolment, instruments, follow-ups, localisation, assisted capture and suppressed reporting | Measurement is not underwriting or proven causal impact. |
| Club treasury | Multiple accounts, cashbook, CSV import/reconciliation and frozen statements | Not full member-capital, NAV/unitisation, distributions or investment-performance accounting. |
| Essentials | Purpose-bound bill/rent finance, named lenders, Cito/CPay integration and servicing | New financial paths require their own accounting, authority, deletion, reservation and recovery acceptance. |
| Developer/AI discovery | Merged searchable Developer Centre and documentation-only MCP bridge | Native machine-readable contracts remain partial; discovery does not authorise money movement. |
| Commercial/governance evidence | Cost/revenue reporting and proposed management-system registers | Customer capital is not revenue; reports and proposals are not profitability or certification. |

## Open financial-control work

[PR #113](https://github.com/lynelk/OpFin/pull/113) remained open, unmerged and conflicting. It proposes financial-readiness, durable-intent, wallet, product/funding/disclosure and reconciliation hardening. Its independent approval and exact-candidate checks remain required.

[PR #118](https://github.com/lynelk/OpFin/pull/118) remained open and unmerged. Its updated record reports candidate `e336daf4962cb735d1ff0b2360690b1eaffab83e` passed 358 tests and 2,362 assertions on both SQLite and PostgreSQL 18, including fresh isolated migrations and separate-session contention. The latest head `af4af405355cffbf5c44941ced66092b3da24f8e` adds documentation. Actual independent APPROVED review remains required before merge.

That scoped branch evidence is not integrated-main acceptance, a production migration or completion of every Essentials accounting/pending-exposure requirement. The verification intentionally stopped before runtime deployment. These are targeted checks, not an exhaustive PR or security sweep.

The separately recorded credential-log incident remains controlled security work; this documentation update does not close it.

## Continuing rules and next work

Personal remains the default Space. New App customers use phone, OTP, names and a six-digit PIN with progressive verification; a second phone is optional. Financial health, credit assessment, non-score reputation and programme measurement remain distinct.

The selected institution is the lender of record. Affiliated participation uses the governed institution/product/funding lifecycle and does not manufacture legal authority. Product configuration is separate from market/channel availability.

Cito/CPay remain preferred integration/payment routes. Explicitly certified direct exceptions remain controlled, with no unsafe retry after an ambiguous original outcome. gnuGrid retains the specific Cito-only route. Stolets remains a separate SME operating product.

Complete outstanding control integration, current-release mobile/database/recovery evidence and normal personal/group acceptance; validate treasury history; activate contracted services; then complete the retained wellbeing, club-accounting, automation and longer-range scope. Keep the essential App-only Individual/Savings Group requirement in force.

GitHub Actions remains deferred by owner instruction. Retain equivalent candidate-specific evidence without enabling Actions, weakening checks or creating unapproved infrastructure. This is a documentation change, not a deployment or financial activation.

## Maintenance

Use the [documentation hub](README.md), [concept](product/OPFIN_CONCEPT_NOTE.md), [evolution review](product/PRODUCT_EVOLUTION_2026-09-26.md), [lender contract](architecture/LENDER_ORCHESTRATION.md), [manuals](manuals/OPFIN_USER_MANUAL.md) and [capability supplement](manuals/CURRENT_CAPABILITY_SUPPLEMENT.md). The dated [concept comparison](product/CONCEPT_AND_PLAN_COMPARISON.md) and [implementation index](product/CANONICAL_IMPLEMENTATION_STATUS.md) retain their own assessment dates.

Original requirements, later decisions, source observations and acceptance evidence are separate authorities. Update this index from new evidence rather than simply a new merge date.
