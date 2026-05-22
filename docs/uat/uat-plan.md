# OpFin Production Replacement UAT Plan

Date: 2026-05-22

This plan defines user acceptance testing for replacing the current live OpFin solution. UAT must be run in a production-like staging environment using migrated or representative live-system data. The current live system remains the source of truth until cutover sign-off.

## Objectives

- Confirm the new OpFin-BE and OpFin-FE support live customer, admin, operations, employer, payment, ledger, support, and reporting workflows.
- Confirm users can complete daily work without relying on demo, mock, or placeholder behavior.
- Confirm old and new systems produce matching outputs during parallel run.
- Capture defects, owners, severity, and go/no-go decisions before cutover.

## Scope

### In scope

- Customer user UAT.
- Admin user UAT.
- Operations user UAT.
- Employer admin UAT if employer-linked benefits or employer eligibility are active in the live system.
- Parallel-run comparisons across live old system and new staging system.
- Defect logging, retesting, and sign-off.

### Out of scope unless active in the live system

- Savings.
- Insurance.
- Investments.
- AI chat/RAG.
- Marketing pages.

If any out-of-scope module is active in the live solution, it moves into UAT scope immediately.

## Environments

| Environment | Role |
| --- | --- |
| Current live system | Source of truth during UAT and parallel run. |
| New staging system | Production-like OpFin-BE and OpFin-FE with migrated data. |
| Provider sandbox/certification | MTN/Airtel/KYC/CRB test environment. |
| Reporting workspace | Stores screenshots, exports, comparison reports, defect logs, and sign-off forms. |

## Test Data Requirements

Prepare migrated or representative records for:

- Customer with no active loan.
- Customer with pending KYC.
- Customer with verified KYC.
- Customer with revoked consent.
- Customer with pending loan application.
- Customer with approved loan offer.
- Customer with active disbursed loan.
- Customer with overdue repayment.
- Customer with failed or pending payment.
- Customer with reversed or corrected payment if present in live system.
- Admin, operations, support, compliance, and employer admin users.
- MTN and Airtel payment records.
- Reconciliation exceptions.
- Audit logs.
- Reports.

## UAT Roles and Owners

| Area | Primary sign-off owner | Supporting owners |
| --- | --- | --- |
| Customer flows | Product owner | Support, compliance |
| Admin flows | Operations lead | Compliance, engineering |
| Operations flows | Operations lead | Finance, support, engineering |
| Employer admin flows | Employer benefits owner | Product, support |
| Financial controls | Finance lead | Engineering, operations |
| Compliance and privacy | Compliance lead | Product, engineering |
| Technical readiness | Engineering lead | DevOps/infra |
| Final go/no-go | Business owner | All owners above |

## Execution Rules

1. Use production-like URLs and environment settings.
2. Disable demo shortcuts and mock API mode.
3. Use only approved test customers or migrated UAT records.
4. Record screenshots or exported evidence for every scenario.
5. Log every defect in `defect-log-template.md`.
6. Retest fixed defects before sign-off.
7. Do not approve cutover while any Critical or High defect remains open.

## Defect Severity

| Severity | Definition | Cutover impact |
| --- | --- | --- |
| Critical | Blocks login, money movement, ledger correctness, customer balance accuracy, provider callbacks, privacy, or compliance. | Blocks cutover. |
| High | Blocks key workflow for a user group or causes incorrect state requiring manual correction. | Blocks cutover unless formally waived. |
| Medium | Workflow works with acceptable workaround and no financial/privacy risk. | Can be fixed before pilot or early rollout if accepted. |
| Low | Cosmetic, wording, or minor usability issue. | Can be fixed after launch if accepted. |

## Acceptance Criteria

UAT passes only when:

- All required customer scenarios pass.
- All required admin scenarios pass.
- All required operations scenarios pass.
- Employer scenarios pass if applicable.
- Parallel-run comparisons meet thresholds.
- No Critical or High defects remain open.
- Medium defects have owners and accepted target dates.
- Sign-off forms are completed by all owners.

## Required Scenario Documents

- `customer-uat-scenarios.md`
- `admin-uat-scenarios.md`
- `operations-uat-scenarios.md`
- `parallel-run-test-plan.md`
- `uat-signoff-template.md`
- `defect-log-template.md`
