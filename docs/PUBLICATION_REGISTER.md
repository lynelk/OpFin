# OpFin publication register

Reviewed: 26 September 2026  
Authority: `docs/PUBLICATION_STANDARD.md`

This register controls how repository documentation should be published or shared. Publication classes identify intended audiences, not access controls; a public repository does not protect a document labelled internal.

## Public documents

These documents are intended to be clean enough for unrestricted external sharing, subject to legal/compliance approval for regulated statements:

- `README.md`
- `docs/CURRENT_STATE.md`
- `docs/README.md`
- `docs/BRAND_IMPLEMENTATION.md`
- `docs/GOOGLE_PLAY_ACCOUNT_DELETION.md`
- `docs/LAUNCH_CUSTOMER_JOURNEY.md`
- `docs/manuals/OPFIN_USER_MANUAL.md`
- `distribution/google-play/listing.md` once the contact/public-URL publication gates are completed

## Controlled external documents

Suitable for the named professional audience rather than unrestricted consumer publication:

- `docs/product/OPFIN_CONCEPT_NOTE.md`, continuity/enhancement review edition 2.1; not new legal, credit or release policy;
- `docs/product/PRODUCT_EVOLUTION_2026-09-26.md`, dated source/status review; not a continuously monitored health report or financial activation;
- `docs/product/OPFIN_PRODUCT_BLUEPRINT.md`
- `docs/product/OPFIN_ESSENTIALS.md`
- `docs/product/INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md`
- `docs/product/PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md`
- `docs/UMRA_DIGITAL_LENDING_CONTROLS.md`
- `docs/COMMUNITY_FINANCE_AND_SACCO_CORE.md`
- `docs/architecture/FINANCIAL_SPACES_DOMAIN_MODEL.md`
- `apps/api/docs/README.md`
- `apps/api/docs/api/API_QUICK_REFERENCE.md`
- `apps/api/docs/api/current-endpoints.md`
- `apps/api/docs/api/frontend-backend-contract.md`
- current architecture/integration documents under `apps/api/docs/architecture/`, subject to security review

The Word derivative of concept note 2.1 carries the same audience and evidence boundaries and appends the dated evolution review. The concept and brand version numbers are separate; formatting in Brand System v3 colours does not freeze the brand release candidate or establish service availability.

## Controlled internal documents

Publication-quality for internal controlled use, but not intended for unrestricted public distribution:

- `AGENTS.md`, component `AGENTS.md` files and `SECURITY.md`;
- `docs/DEVELOPER_START_HERE.md`;
- `docs/FINANCIAL_CHANGE_GOVERNANCE.md`;
- `docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md`;
- `docs/manuals/OPFIN_TRAINING_MANUAL.md`;
- `docs/manuals/OPFIN_OPERATIONAL_MANUAL.md`;
- `docs/manuals/OPFIN_UAT_MANUAL.md`;
- `docs/product/CANONICAL_IMPLEMENTATION_STATUS.md`;
- `docs/product/IMPLEMENTATION_BACKLOG.md`;
- `docs/governance/INTEGRATED_MANAGEMENT_SYSTEM.md`, a policy proposal awaiting evidenced adoption, not an effective-policy or certification claim;
- `docs/governance/ISO_READINESS_ACTION_REGISTER.md`, an implementation/evidence register, not proof of conformity;
- operational, production, security and UAT documents under `apps/api/docs/`;
- `apps/web/docs/` technical/audit/production material;
- `infrastructure/railway/` and `docs/operations/`;
- Google Play release automation/checklist/Data Safety/financial-features/reviewer-notes worksheets.

## Historical evidence

The following paths retain historical context and must not be presented as current product policy:

- `apps/api/docs/audit/`
- `apps/api/docs/demo/`
- `apps/web/docs/audit/`
- `apps/web/docs/demo/`
- dated production assessments/checkpoints where the document itself states an earlier evidence date;
- `docs/migration/`;
- `docs/releases/`;
- dated gap analyses where superseded by current canonical status.

Historical evidence can be published as historical evidence if its original date, scope and limitations remain clear. The 24 September comparison and delivery records are not silently rewritten by the 26 September concept review; use the new current-state index for the fresher evidence.

## Known publication gates

The following facts are deliberately not fabricated and must be supplied/verified before the affected external publication:

### Google Play listing
- approved developer/legal entity wording;
- primary support email;
- support telephone;
- verified public website/custom domain;
- verified privacy-policy URL.

### Google Play financial-features declaration
- approved maximum repayment period;
- approved maximum fee-inclusive APR;
- representative approved pricing example;
- legally correct lender/facilitator role and licence/provider evidence.

### Play reviewer notes
- dedicated reviewer account;
- reviewer PIN entered only in Play Console;
- reviewer-safe OTP/test-number instructions.

These are release inputs, not editorial defects. Documents carrying them are classified Controlled internal until the values are supplied and approved.

## Publication sign-off

A document moves to Public or Controlled external status only when:

- content matches current `main`;
- unresolved factual gates are completed or clearly excluded from the published artefact;
- `make publication-check` passes;
- required legal/compliance/brand approval has been obtained for the intended audience.

This register identifies intended audience and review boundaries. It does not claim those approvals, full-repository validation or financial activation have occurred merely because a documentation change is committed.
