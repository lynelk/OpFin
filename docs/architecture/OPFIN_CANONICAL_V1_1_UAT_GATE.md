# OpFin Canonical v1.1 — implementation and UAT gate

Status: **Implementation in progress; not certified UAT-ready**.
Baseline: seven logical experiences: Personal, Invest, Groups, People, Enterprise, Partners, Admin. These are role-aware experiences, not a mandate for seven deployments.

## Source-of-truth rules
- One person identity; tenant-scoped Financial Spaces; personal data never disclosed to employers or groups by membership alone.
- Groups: investment clubs, SACCOs, savings clubs and VSLAs all see the same *available* catalogue (membership/KYC, savings, contributions, shares, loans, investment portfolio, dividends/distributions, governance, approvals, statements, accounting, deposits, guarantees, fines, reporting, import). Availability is not activation: licensing, eligibility, product configuration and controls must gate financial actions.
- People: ten mandatory Core Release capabilities: HR, leave, payroll, attendance, field accountability, asset management, KPI tracking, biometric connectors, appraisals, rewards. The People service must remain standalone; embedded Flutter access and employer web portal must use authenticated, tenant-scoped contracts.
- Preserve the existing financial ledger and institution/lender-of-record controls. No direct financial execution from HR; consent-bound employment verification only.

## Engineering worklist
- [ ] Inventory existing web/mobile routes, role maps, Financial Space types and module gates; map each canonical experience to existing navigation rather than duplicate it.
- [ ] Introduce a server-authoritative group capability catalogue for all four group types; test visible-but-disabled versus permitted operations and ensure SACCO-specific endpoints cannot be used by other types without a reviewed common abstraction.
- [ ] Implement/test organisation provisioning, membership, maker-checker, audit, financial ledger segregation, imported history and cross-tenant denial.
- [ ] Complete ten People modules end-to-end, not placeholder cards, and test offline attendance/field queues, statutory payroll, device connectors, approvals and standalone operation.
- [ ] Complete identity federation, exact Business Space linking, purpose-specific consent and revocation; do not infer identity or linking from names.
- [ ] Ensure role-aware Personal/Invest/Groups/People/Enterprise/Partners/Admin navigation and deny direct API access outside permission.
- [ ] Validate financial reconciliation, provider contracts, backup/restore, audit/security, mobile and web releases.

## UAT acceptance matrix
| Area | Minimum pass criterion |
| --- | --- |
| Identity | One verified person switches roles without balance/data leakage |
| Groups | All four group types can discover every module; prohibited activities fail closed |
| Governance | Two distinct actors required for controlled approvals; durable audit trail |
| Money | Ledger balanced; retries idempotent; external settlement independently confirmed |
| People | All ten modules complete their source-defined end-to-end scenarios |
| People isolation | HR operates during OpFin outage; no private financial data leaks |
| Payroll | Effective-dated Uganda rules verified, two-person approval, locked period, reconciled export |
| Consent | Employment disclosure requires purpose-specific grant; revocation stops future disclosure |
| Access | Cross-tenant, revoked-role and privilege-escalation tests fail closed |
| Release | API, web, Flutter, integration and security checks pass on exact candidate SHA; staging deployment and rollback tested |

## Evidence required before 'ready to test'
Record release candidate SHA, CI run URLs, database migration and rollback results, staging URL/build identifiers, seeded non-production personas for all roles, test cases and actual results, unresolved severity-ranked defects, and product/security/finance sign-off. No live-money UAT without independent regulatory and settlement clearance.

## Current assessment
Repository documentation `docs/architecture/OPFIN_PEOPLE_CORE_RELEASE_SCOPE.md` explicitly marks scope implementation pending; `docs/architecture/OPFIN_PEOPLE_INTEGRATION.md` is a contract, not proof of live integration. `docs/CURRENT_STATE.md` notes unresolved operational evidence. Consequently UAT readiness is **not established** by this document.
