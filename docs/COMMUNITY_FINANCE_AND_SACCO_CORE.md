# OpFin Community Finance and Member Cooperative Core

This document defines the member-friendly naming, product boundaries, dormant activation controls and implementation scope for OpFin's community finance foundation.

The foundation is intentionally built but not activated. It must not collect member shares, savings, investment funds, insurance premiums, asset deposits or employer deductions until the approved activation runbook, licensing/partner checks, custody model, disclosures, support readiness and reconciliation drills are complete.

## Member-friendly terms

| Internal term | Public term | Short term | Member-facing meaning |
| --- | --- | --- | --- |
| Regulated community capital pools | Community Growth Circles | Growth Circles | A transparent member-backed pool for community finance, opened only after governance, custody and regulatory checks are complete. |
| Insurance and asset finance | Protection & Asset Plans | Protection Plans | Practical protection and asset pathways offered through approved partners. |
| Employer-backed lending | Workplace Support Finance | Workplace Finance | Employer-supported savings, welfare and credit where salary or payroll information supports affordability and repayment arrangements. |
| Behaviour scoring | Member Growth Score | Growth Score | A transparent score that explains financial habits, platform conduct, community participation and product behaviour. |
| SACCO Core | Member Cooperative Core | Cooperative Core | The SACCO operating foundation for memberships, shares, savings, guarantors, committee approvals, statements and cooperative records. |

The public terms should be used in app, web, support, training and member communications. The internal terms remain useful for legal, risk, audit and engineering controls.

## Activation posture

Default posture: **Dormant Ready**.

The following environment flags keep the foundation closed:

```env
OPFIN_COMMUNITY_FINANCE_MODE=dormant
OPFIN_COMMUNITY_FINANCE_LIVE_ENABLED=false
OPFIN_SACCO_CORE_ENABLED=false
OPFIN_COMMUNITY_FINANCE_PUBLIC_ROUTES_ENABLED=false
```

Production configuration deliberately blocks live-mode activation until a future, separately reviewed activation change is approved. This is by design. Accidental activation of a money product is not a product launch; it is a mess with a login screen.

## Required sign-offs before activation

Before activation, the following must be recorded and reviewed:

1. Board or product committee approval.
2. Licence or regulated partner confirmation.
3. Funds custody and settlement model.
4. Member terms and plain-language disclosures.
5. Risk limits and loan-loss reserve policy.
6. Data protection and consent review.
7. Operations playbook and member support readiness.
8. Production reconciliation and recovery drill.

## Built foundation

The dormant schema supports:

- Community finance programmes.
- Memberships and member numbers.
- Employer-linked memberships.
- Share-capital requirements and balances.
- Savings balances.
- Ledger accounts and idempotent ledger entries.
- Community finance facilities.
- Workplace finance, cooperative facilities and community-backed credit records.
- Guarantor requests, consent references and release tracking.
- Explainable Member Growth Score scorecards.
- Protection and asset partner plans.

## Member Growth Score

The composite score is called the **OpFin Growth Passport**. The visible score is called the **Member Growth Score**.

The composite score is intentionally explainable. A reviewer can break it down into:

| Component | Weight | Purpose |
| --- | ---: | --- |
| Financial Habits | 40% | Savings, repayment discipline, affordability, income/cashflow stability and obligation pressure. |
| Platform Trust | 20% | Identity verification, consent reliability, account security, support conduct and data completeness. |
| Community Participation | 20% | Group savings, guarantor reliability, membership duration, workplace/group standing and community obligations. |
| Protection & Asset Readiness | 10% | Cover continuity, asset deposits, asset care/usage and partner confirmation. |
| Responsible Use | 10% | Fraud-risk absence, complaint resolution, terms adherence and financial education progress. |

Missing factors are not silently scored as failure. They reduce confidence and remain visible in the breakdown so a member or reviewer can understand what data was available and what was missing.

No automated adverse action, price increase, limit reduction or live credit decision should rely on this score until the score policy, validation evidence and member disclosures are approved.

## Module boundaries

### Community Growth Circles

Allowed before activation:

- Configuration.
- Readiness review.
- Simulation.
- Staff training.

Blocked before activation:

- Member deposit collection.
- Investment acceptance.
- Loan disbursement.
- Return distribution.

### Protection & Asset Plans

Allowed before activation:

- Partner due diligence.
- Pricing simulation.
- Disclosure review.

Blocked before activation:

- Policy issuance.
- Premium collection.
- Asset disbursement.
- Supplier settlement.

### Workplace Support Finance

Allowed before activation:

- Employer setup.
- Eligibility simulation.
- Payroll mapping.

Blocked before activation:

- Salary deduction.
- Live loan acceptance.
- Employer float drawdown.

### Member Cooperative Core

Allowed before activation:

- Schema migration.
- Admin configuration.
- Dry-run member import.
- Training.

Blocked before activation:

- Member share collection.
- Member savings collection.
- Credit committee approval.
- Dividend distribution.

## Implementation notes

The foundation is backend-first. No public app routes or live product exposure are enabled by this change. That prevents users from seeing, joining, funding or borrowing through the dormant community finance layer before the operating model is approved.

When activation is later approved, implementation should add:

- Authenticated member-facing routes.
- Admin review and approval workflows.
- Partner settlement hooks.
- Reconciliation evidence.
- Support and complaints workflows.
- Production monitoring and reporting.
- Country-specific regulatory pack for Uganda first.
