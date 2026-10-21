# OpFin documentation alignment and release-evidence register

**Classification:** Controlled internal, with links to public and controlled-external references  
**Reviewed:** 21 October 2026  
**Repository:** `lynelk/OpFin`  
**Source baseline:** `c04d72f894aba163e6c8aaae668d8ad35a7af731` (`main`, documentation review starting point)  
**Language:** English (United Kingdom)

## Purpose and source authority

This register records what the current repository actually supports, what requires verification and which documents own the instructions. It is a **documentation review**, not a financial launch sign-off, an externally certified API specification, a security incident closure, or an assertion that every user can access every feature in production.

Priority of evidence: approved product and regulatory decisions; current implementation and feature flags; exact-revision tests and independent reviews; deployment records and provider finality; then dated historical reports. A deployed route does not prove provider activation or a successful customer outcome. Dated audit/incident documents remain unchanged as historical evidence even when later fixes land.

## Current implementation map

| Capability | Evidence in source/docs | What the docs may claim | What they must not claim |
| --- | --- | --- | --- |
| Identity, Personal and Financial Spaces | API services and current domain/API references | One continuing identity with tenant/role boundaries and progressive verification | That another Space or role can read personal financial history |
| Financial Life and guidance | `docs/product/OPFIN_P0_P1_UNIVERSAL_FINANCIAL_LIFE.md`, product code, current Web/App | Goals, aspirations, learning and financial wellbeing remain distinct from underwriting | That financial guidance is a guaranteed outcome or product eligibility |
| Lending, lenders and investors | `docs/architecture/LENDER_ORCHESTRATION.md`, `docs/product/OPFIN_FINANCE_EVIDENCE_LED_CREDIT_PROGRAMME.md` | Source support for governed offers, partner distribution, lender authority and growth-readiness inventory | That configured products imply live funding, custody, legal authority or an unrestricted investor marketplace |
| Investment clubs | `apps/api/routes/club_accounting.php`, `apps/api/routes/club_client_requests.php`, [club API](../../apps/api/docs/api/CLUB_ACCOUNTING.md), [recovery contract](../../apps/api/docs/api/CLUB_CLIENT_RECOVERY.md) | Separate capital/ownership books, guided accounting tasks, immutable statements, saved-request replay, retained own-history and exports in source | That club accounting approval moves money or a system print/share dialogue proves delivery |
| Essentials | `apps/api/routes/essentials_collections.php`, [servicing contract](../../apps/api/docs/api/ESSENTIALS_DURABLE_COLLECTIONS.md) | Durable collection identity, pending reservations, provider observation, original-policy allocation and reversals | That all drawdown, biller-settlement, lender release, activation journals, legacy adoption and customer-refund lifecycles are certified |
| Financial Intelligence and statement analysis | `apps/api/routes/financial_intelligence.php`, [API contract](../../apps/api/docs/api/FINANCIAL_INTELLIGENCE_CONTRACT.md), [runbook](FINANCIAL_INTELLIGENCE_RUNBOOK.md) | Source-referenced, feature-gated analytics; original-statement analysis and review/appeal workflows | That an issuer, uploaded CSV/PDF, bank statement or source observation is independently authenticated merely because it parses |
| SACCO, white-label and commercial actions | `apps/api/routes/sacco_core.php`, [completion plan](../development/SACCO_AND_WHITE_LABEL_COMPLETION_2026-09-29.md) | Source-defined products, member contexts, approval/case routes and brand requests | That regulated SACCO services or arbitrary branded hostnames are activated without institutional and DNS/TLS evidence |
| Payroll and referral | `apps/api/routes/payroll_deduction.php`, [account/referral contract](../../apps/api/docs/api/ACCOUNT_AND_DATA_DELETION.md) | Case-bound payroll instructions, customer-confirmed partner financial intents and safe cancellation/reconciliation | That a referral is a credit approval or an external payroll reservation is assumed released |
| Institutional accounting/ECL | [decision pack](../finance/INSTITUTIONAL_RECONCILIATION_AND_SHARED_EXPOSURE_DECISION.md) | Synthetic, non-production preview and provisional model/code evidence | That shared receivables, real lender ledgers or IFRS 9 methodology have independent signed acceptance |
| Cito and third-party integrations | [integration matrix](../integrations/CITO_OPFIN_DELIVERY_MATRIX.md) | Provider orchestration through a governed integration boundary subject to country/capability permissions | That an intermediary is itself a licence, final settlement, direct official identity authority or universal failover |
| Developer Centre and AI discovery | `apps/api/routes/developer.php`, [developer guide](../../apps/api/docs/api/DEVELOPER_INTERFACE.md) | Searchable discovery, role-filtered catalogue, checked-in guides and reviewed-only OpenAPI export | That every registered route has reviewed schemas, or that an AI tool can execute financial operations |

## Release and incident distinctions

At the review baseline, GitHub combined commit status reported **success** for the API, Web, worker and scheduler deployment contexts for `c04d72f...`. This is deployment-status evidence only. It is not a newly executed feature acceptance, App Store/Play Store release, live provider settlement, independent legal review or full release-candidate test suite.

The 17 October security triage identified **35 historical sensitive-artifact objects**, not 35 confirmed live secrets. Independent restricted investigation, applicable revocation/rotation, offsite restore proof and an owner acceptance record remain distinct obligations. Read [security triage](SECURITY_HISTORY_TRIAGE_20261017.md); do not publish credential details.

The [six-issue record](ISSUES_17_103_128_136_176_190_STATUS.md) and [institutional decision pack](../finance/INSTITUTIONAL_RECONCILIATION_AND_SHARED_EXPOSURE_DECISION.md) preserve unverified issuer authenticity, merchant consent, partner arrangements, signed institution balances and investor/lender activation as explicit evidence gates. Synthetic fixtures are never substituted for these.

## Who uses which guide

- Customer: [user manual](../manuals/OPFIN_USER_MANUAL.md), then [capability supplement](../manuals/CURRENT_CAPABILITY_SUPPLEMENT.md).
- Trainer: [training manual](../manuals/OPFIN_TRAINING_MANUAL.md) and [role-based exercises](../manuals/ROLE_BASED_TRAINING_PATHS.md).
- Operations and support: [operational manual](../manuals/OPFIN_OPERATIONAL_MANUAL.md), then feature-specific runbooks.
- Tester: [UAT manual](../manuals/OPFIN_UAT_MANUAL.md); record actual environment, revision, actor, expected/observed behaviour and evidence.
- Novice or experienced integrator: [API entry](../../API.md), [integrator guide](../../apps/api/docs/api/INTEGRATOR_GUIDE.md), then per-domain contracts.
- AI integrator: [read-only developer interface](../../apps/api/docs/api/DEVELOPER_INTERFACE.md) and [AI contract](../developer/API_AND_AGENT_PLATFORM.md).

## Publication and continuous update rules

1. New or changed routes must update the current endpoint index and any affected field-level API contract, examples, errors and authorisation.
2. Changed customer or officer tasks must update the corresponding user/training/operations/UAT instructions; a feature flag or pending release must be identified plainly.
3. Financial-state changes must document idempotency, provider uncertainty, ledger posting, reversals, permission denial and independent acceptance evidence.
4. The Developer Centre catalogue inventories registered routes automatically but does **not** infer business meaning from method/path alone. The strict reviewed-schema gate is separate.
5. Run `make docs-check`, `make publication-check`, `make api-docs-check` and affected application suites before merge. Record the exact result; do not copy historical passing counts.
6. Maintain British English and preserve exact route/field spellings. Respect publication classes in [publication register](../PUBLICATION_REGISTER.md).

**Review outcome:** current reference documentation updated to describe source and limitations. No new API/provider activation, financial transaction or production configuration change is authorised by this document.
