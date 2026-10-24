# OpFin Digest Controls: Approved Policy and Implementation Specification
Date: 24 October 2026
Status: Management direction approved; technical deployment and external professional opinions pending.

## 1. Evidence completeness gate (P0)
Before any live automatic credit approval, require attributable, current, purpose-consented evidence for identity, applicant authority, affordability, existing exposure, product terms and applicable credit-policy checks. Record source, retrieval time, validity, consent reference, rule version and immutable decision audit identifier. Missing, stale, conflicting or unverifiable mandatory evidence must fail closed to manual review or rejection under existing policy, never silently default to favourable values. Record structured reason codes. Keep protected characteristics and programme participation outside credit scoring.

Acceptance: automated tests for missing, stale, revoked, duplicate and conflicting evidence; verify no disbursement proceeds after failed mandatory gate; test tenant separation and audit replay.

## 2. Portfolio reconciliation gate (P0)
Reconcile daily at transaction and portfolio level: loan principal and interest schedules, fees, cash disbursements, repayments, reversals, suspense, provider statements, customer balances and general ledger. Match by stable external/internal transaction identifiers, currency, value date and amount; detect duplicates, missing entries, partial settlements, unmatched reversals and ageing breaks. Prevent final settlement/close-out and unsupported financial reporting for unreconciled items; quarantine exceptions with accountable owner, SLA and documented resolution. Never manufacture a balancing entry. Escalate material breaks to Finance and Risk; retain maker-checker approval and immutable evidence.

Acceptance: daily reconciliation reports with opening balance + movements = closing balance; exception ageing, counts and amounts; independent sign-off and regression tests. Existing ledger posting rules remain unchanged until accounting design approval.

## 3. Tier IV tax treatment memorandum (P0 commission)
Commission independent Uganda-qualified tax and legal professionals to provide a dated written opinion covering entity licensing/classification, interest and fee characterisation, VAT applicability and exemptions, withholding obligations, corporate income tax, bad-debt provisioning/deductibility, investor/P2P arrangements, digital invoicing/EFRIS applicability, stamp duties if relevant, reporting and historical exposure. Require citations to current legislation, URA guidance, regulator positions, worked examples for each product and customer flow, uncertainty matrix, recommended accounting treatment, filing calendar and implementation sign-off. No automatic tax-rule change before signed opinion and Finance/Legal approval.

## 4. Climate risk scenarios (P1, research only)
Create versioned Uganda-specific exploratory scenarios for drought, flooding, food-price shock, agricultural supply disruption and regional income stress. Document sources, exposure mapping, assumptions, uncertainty, scenario severity, horizon, data limitations and sensitivity of arrears/PAR30/PAR90, defaults, collections and capital/liquidity. Use aggregate and de-identified data where possible. This is portfolio monitoring and scenario preparation only; do not change current approval, pricing, limits, collections or eligibility rules.

## 5. Transaction-data underwriting shadow mode (P0 safety invariant)
Transaction-derived features may run only in a separately permissioned shadow pipeline with explicit lawful consent, purpose limitation, provenance, freshness and retention controls. Store predictions separately from production decision inputs. No write access from shadow output to eligibility, offer, credit limit, interest, fees, disbursement or collections. Capture Uganda cohort-level outcomes and compare with baseline using out-of-time validation, calibration, discrimination, stability, fairness, data quality, adverse outcomes and drift. No production promotion until sufficient local outcomes, independent Risk/Compliance validation, documented policy approval, customer protection review and tested rollback. Missing Uganda evidence means remain shadow-only.

## Release and ownership
Engineering: implement gates behind safe feature flags and tests. Finance: own reconciliation and tax instruction. Risk/Compliance: own evidence criteria, climate methodology and shadow-model governance. Legal/Tax: commission and approve professional opinion. Release manager: verify staging evidence and sign-off before production. Existing live approval policy must not be modified by this directive.

## Definition of done
No workstream is marked implemented merely because this document exists. Require merged code, passing tests, staging acceptance evidence, owner sign-offs and monitored production verification for the two P0 gates. The memorandum is complete only when a signed professional opinion is delivered; climate and shadow work remain non-decisioning by design.
