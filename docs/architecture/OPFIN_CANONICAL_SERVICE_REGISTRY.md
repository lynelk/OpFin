# OpFin canonical service registry

Status: **Target architecture and discovery API implemented on development branch; not a production activation claim.**

## Authoritative registry

Machine-readable source: `apps/api/config/opfin_service_registry.php`.
Authenticated discovery: `GET /api/service-registry`.

The registry identifies Financial Space types, 13 customer-facing service families, 10 shared tools and the 10 mandatory OpFin People modules. It is intentionally metadata, **not** a substitute for live membership, role, commercial entitlement, regulatory eligibility, product activation or readiness checks.

## Ownership and reuse

- Financial Spaces own membership and context boundaries.
- Financial records own shared budgets, assets, debts, cash flow and goals.
- Accounting owns ledger semantics; governance owns approvals, tasks and documents.
- Savings Groups and SACCO finance own member savings and cooperative records; investment operations own investment club units and valuations.
- People owns workforce engagement, payroll, attendance, biometric event integrations, assets in custody, appraisals, rewards, KPIs and field accountability.
- Credit and risk own regulated underwriting and lending, with financial core immutable accounting, durable money intents and reconciliation.
- Integration adapters, including Cito, never confer regulatory authority merely by being connected.

Shared tools can be used in multiple Space contexts, but **records are not shared between Spaces**. An employer-owned asset allocated to an employee is not the employee's personal property; payroll deductions are not automatically personal credit liabilities; member loans have different legal and ledger treatment from personal debt records.

## User experience

Keep the existing OpFin mobile app shell and its financial journeys. Use authorised Financial Space switching and contextual, role-aware feature discovery. OpFin People is embedded in the Flutter app for the Core Release, while retaining independently owned backend data and reusable Flutter features for a later dedicated app.

## Delivery sequence

1. Enforce authoritative membership/entitlement/regulatory gates per service.
2. Build shared tools as space-scoped domain contracts with migration compatibility.
3. Implement each service family against those contracts; preserve the existing immutable financial core.
4. Integrate all ten People modules in one Core Release.
5. Add mobile navigation and web management surfaces based on authorised capabilities, not the registry alone.
6. Test tenant isolation, accounting reconciliation, multi-membership switching, migration/import, offline operation and app regression.
7. Release only after CI, security, regulatory and operational readiness gates pass.

No new persistent infrastructure or production activation is implied by the registry.

## Product terminology (20 October 2026)

**Savings Groups** is the canonical user-facing name for savings circles, VSLAs and informal community savings groups. The service registry key is `savings_groups`; the database Financial Space type **remains** `savings_group` to preserve existing memberships, APIs, imports and accounting history. Existing references to VSLAs may remain in educational or regulatory classifications, but are not the primary user-facing module label.

## Confirmed implementation progress

- Role-aware server-sourced workspace permissions attached to each active Financial Space membership.
- Flutter workspace switcher integrated with Home and Financial Space detail routes, with restricted edit/invite UI.
- Financial asset and debt mutations restricted to authorised financial managers at the API boundary.
- Shared budgets use the existing `financial_budgets` table with mandatory `financial_space_id` scoping, space-based view/create/update APIs and a Flutter budget view.
- Budget values are **planned limits only**. Expense actuals, reconciliation and overspend alerting remain separate integration work; nothing is marked complete before actual evidence.

These changes are on the development branch and remain subject to full automated and end-to-end tests, review and release approval. No People payroll/biometric/HR suite or regulated institution operation is implied.
