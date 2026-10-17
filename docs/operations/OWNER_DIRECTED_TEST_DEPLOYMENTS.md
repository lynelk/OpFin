# OpFin: Owner-Directed Production Test Deployments

**Effective:** 17 October 2026
**Status:** Product-owner deployment directive. This replaces the assistant-imposed discretionary hold on test deployments; it does not repeal financial, customer-data, platform or statutory controls.
**Environment:** Existing Railway `OpFin / production` on the existing `lynelk/OpFin` GitHub `main` deployment integration.

## Owner controls deployment timing

The product owner may explicitly request **merge to main and deploy to production for testing** at any time. Do not substitute prolonged release ceremonies, mandatory new restore drills, manual screenshots, a fixed count of vintages, user-acceptance ceremonies, or discretionary product reviews for the owner's deployment instruction.

**Direct commands such as "merge and deploy now" authorise the GitHub/Railway release action.** A command to *permit future* deployments does not itself authorise an immediate merge. Do not automatically merge other draft PRs or deploy unrequested application changes.

## How to execute an owner-requested test deployment

1. Resolve the exact requested commit/PR and ensure the target is the existing OpFin `main` and production environment. Report what is included and excluded.
2. Check automatic CI results that already exist; **do not require additional manual QA or new backup/restore exercises as a prerequisite to a product-owner-directed test deployment**. CI failures or missing evidence are disclosed clearly as risks; fixing failed source compilation or incompatible migrations is a functional necessity, not an optional checklist.
3. Prefer a merge that preserves the passing commit; do not disable CI/security checks, weaken authentication/Space boundaries, remove regulated-service eligibility, bypass actual lender/investor/custody authorisations or alter payment/ledger controls to accelerate deployment.
4. Use the existing GitHub-to-Railway production deployment path. Do not create new Railway projects, databases, services, volumes, buckets or environments without the owner's separate approval.
5. Watch the deploy status, required migration outcome, API readiness, Web access, worker and scheduler operation. Report failures immediately and facilitate rollback where possible.
6. State **deployed for testing**, never **accepted for general financial-product release** solely because a build succeeded. Record outstanding issues and financial data integrity questions without making discretionary sign-off a veto on the owner's testing decision.

## Restore / backup risk policy

PITR and volume backups remain enabled as already configured. Keeping existing backup coverage is strongly recommended; use it when available. **Do not demand a new restore drill, comparison report, human attestation or a separately created migration backup on every owner-initiated test release** merely because the code contains additive migrations.

A full production database replacement, deliberate volume restore, destructive data migration or other irreversible financial-data operation is a different action and requires deliberate disclosure and owner approval of the **specific** destructive action. No instruction to "deploy" includes permission to overwrite the live database, restore a volume in place, erase customer accounts, lose financial records or execute payments.

The 17 October 2026 restored-volume/history discrepancy remains an operational item to resolve, and customer financial record reconciliation should be undertaken. **It does not serve as an automatic assistant-imposed block on non-destructive application testing when the product owner explicitly directs deployment**, provided technical and platform requirements permit it. Do not claim the discrepancy is resolved.

## Product and financial safeguards continue

- Test/release velocity does not authorise moving real customer funds without approved actual product authority, financing/custody, affordability, repayment and settlement controls.
- New child financial-product access, provider integrations and new lender/investor rails require their product-specific permissions before enabling transactions.
- Existing money and customer data protection checks remain in their implemented paths.
- Growth in authorised borrowing and investor participation may proceed immediately; no blanket time, cohort or vintage threshold limits apply.

## Owner-requested release summary template

- **Request:** exact user instruction and target PR/commit
- **Scope:** changes included; anything intentionally excluded
- **Automatic checks:** pass/fail/unknown (reported, not presented as an arbitrary owner-release gate)
- **Risk notes:** concise, clearly labelled; especially migrations/data state
- **Action:** merged/failed, target SHA, Railway deployment IDs and observed health
- **Result:** live for testing / deploy failed / rolled back; pending fixes

This directive does not claim that any previously open PR has already merged, nor that production has already deployed the new code.
