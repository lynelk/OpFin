# OpFin Open-Issue Execution and Closure Register

**Reviewed:** 23 October 2026  
**Repository:** `lynelk/OpFin`  
**Status:** Review/coordination document only. No financial rail activation, production change, acceptance sign-off, or completed implementation is claimed.

## Rules for closing an issue

1. Close as **completed** only when its own definition of done has reproducible implementation and test evidence, plus required operational or external approval.
2. Close as **duplicate/not planned** only when a named successor accepts *every* unfinished requirement, with bidirectional links and no lost accountability.
3. Keep implementation merges distinct from production release, regulated authorisation, security-incident closure and financial settlement evidence.
4. Do not treat a green CI run, a checklist copied from a branch, or a demo as evidence of provider authorisation or production readiness.
5. Never place credentials, private customer records or unredacted incident artefacts in public GitHub issues.

## Current issue decisions

| Issue | Priority | Remains open because | Next verifiable evidence |
| --- | --- | --- | --- |
| [#17](https://github.com/lynelk/OpFin/issues/17) Production recovery, security and CI governance | P0 | Offsite backups and isolated logical/PITR restores, Railway check-suite gating, rulesets and historical secret response require verification | Redacted restore drill with measured RPO/RTO; independent encrypted backup; proof CI gating and branch protection; privately signed credential rotation and history scan |
| [#206](https://github.com/lynelk/OpFin/issues/206) Unified Core Release | P0 | Ten embedded People modules, Savings Groups, SACCO and club operations, Space-wide isolation and release acceptance remain incomplete | Merged source + API/mobile/web tests for each module and scoped workflow, migrated sample data and documented UAT/security approvals |
| [#190](https://github.com/lynelk/OpFin/issues/190) Authorised lending and investor growth | P0 | Country/product approval, funded rails, user journeys, suitability/affordability and settlement acceptance are not established by the issue text | Lender/product/channel activation matrix and signed permission, treasury, disclosures, reconciliation and operational readiness per live rail |
| [#176](https://github.com/lynelk/OpFin/issues/176) Optional Stolets merchant finance | P1 | Merchant passport is an optional external data input, not an automatically authorised finance route | Independent consent and signed API/replay controls, borrower and business permission, contract tests, approved payments and lending acceptance |
| [#128](https://github.com/lynelk/OpFin/issues/128) Uganda statement authenticity | P1 | Generic PDF parsing does not authenticate Uganda statement issuers | Authorised samples and versioned MTN/Airtel/bank adapters, documented genuine-source checks and provider failure tests |
| [#136](https://github.com/lynelk/OpFin/issues/136) Capital verticals | P2 | Architecture foundations do not complete vehicle/device/productive-asset financing lifecycles | Per-product evidence for supplier payments, servicing, refunds, asset rights, recovery, accounting and approved provider/regulatory gates |
| [#103](https://github.com/lynelk/OpFin/issues/103) Brand System v3 freeze | P2 | Release-candidate assets do not equal legal/trademark, visual and accessibility approval | Outlined masters, real-device/print QA, trademark disposition, store evidence, named owner and package version approval |

## Dependency and ownership boundaries

- **#17 is the release-governance gate**, not a generic blocker to all source development. Continue isolated engineering while deployment and incident acceptance remain controlled.
- **#206 owns the unified Core Release.** Existing mobile app delivery and People module acceptance remain part of its criteria. Do not merge partially built features into an asserted production Core Release.
- **#190 owns growth of already authorised/funded rails.** It does not impose experimental Stolets cohorts or maturity-vintage restrictions on unrelated lending. No live-money switch without permission and settlement controls.
- **#176 remains optional**, limited to a separately consented, independent integration. OpFin remains personal-first and must not require Stolets.
- **#128, #136 and #103 each require domain-specific evidence**; none is fully subsumed by #206.

## Immediate execution checklist

### 1. Security and reliability: #17
- [ ] Obtain privately retained incident inventory; prove key rotation/revocation and downstream consumer migration without publishing secret values.
- [ ] Demonstrate encrypted independently controlled logical backup and recover into an isolated destination; retain redacted verification log, RPO and RTO.
- [ ] Demonstrate PITR isolated restore without risking the production database.
- [ ] Enable/verify Railway wait-for-CI on API, web, worker and scheduler and GitHub protected main rulesets. Obtain current-state evidence, not configuration intent.
- [ ] Record operator/security acceptance and rollback drill before issue closure.

### 2. Core Release: #206
- [ ] Produce source-to-test matrix for each of the ten People modules, as well as Savings Groups, SACCO, investment clubs and shared tools.
- [ ] Verify cross-Space authorisation on every relevant endpoint, membership removal, expiry and offline transitions.
- [ ] Exercise immutable accounting, opening-balance backfills and rollback with representative but non-sensitive fixture data.
- [ ] Run API, web, Android/iOS and security CI; conduct supervised UAT and record sign-offs.

### 3. Permitted growth: #190
- [ ] Build country-by-country product/partner activation inventory with statuses `demo`, `review`, `signed-not-funded`, `active`.
- [ ] Require actual legal authority, lender-of-record, funding, custody, money movement, reconciliation and total-cost disclosures for each `active` route.
- [ ] Verify app borrower/investor journeys, hardship and complaints, and PAR1/7/30/90 reporting.
- [ ] Grow eligible existing rails only after their own evidence-based activation decision.

## Supersession review

At the date of review, **none of the seven open issues is demonstrably wholly superseded**. Apparent overlaps are *workstream dependencies*, not duplicates. Previous issue consolidation includes [#20](https://github.com/lynelk/OpFin/issues/20) into #17 and closure of [#140](https://github.com/lynelk/OpFin/issues/140), but a closed tracker is not evidence that the epics were delivered.

## Updating this register

For any proposed issue closure, attach: exact source PR/commit, CI run URL, acceptance test result, operational/regulatory approval as applicable, rollback evidence, named reviewer and date. Update the receiving issue before closing a superseded issue. Do not close an issue merely to simplify the dashboard.
