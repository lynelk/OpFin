# OpFin People integration with the canonical OpFin platform

Status: implementation contract and release checklist, not proof of deployed integration.

## Source of authority

- OpFin `apps/api` remains authoritative for person identity, authentication, consent, Financial Space membership, eligibility, lending, money and financial records.
- OpFin People is a separately deployed Laravel HR system with independent tenant data, employment lifecycle, leave and payroll preparation. Its Next.js employer portal must not read OpFin financial records.
- The OpFin Flutter client's `More` area may link to `My Employment` after an actual authenticated, person-specific integration is available. Do not create a second login silently.
- OpFin's `FinancialSpace.public_id` is the external stable Space reference. A People tenant may map to an OpFin Business Space only after an authoritative administrator-approved linking flow. Never infer a match by business name, contact or tax identifier.

## Default integration and independent consent

Integration capability is enabled by default for newly provisioned People tenants, with outbound employment disclosures disabled until purpose-specific authorisation has been verified. An HR-only employer and employee must still be able to operate without an OpFin account. Mere enrolment in an employer, a Space role, or the default integration setting never grants credit eligibility or permission to use private financial history.

## Proposed minimum upstream contract (implementation requires review)

1. A verified authenticated person context, issued or validated server-side by OpFin. No user IDs, phone numbers or roles supplied by browser query parameters may be treated as authority.
2. An authoritative **exact Business Space** membership check for that person and linked public Space ID, returning only a minimal `active` and action-specific permission result, with appropriate cache invalidation/revocation and audit.
3. A separate privileged employer linking operation requiring authority in both systems and explicit agreement. Store a unique mapping `(people_tenant_id, opfin_space_public_id)` and reject conflicting links.
4. HR data disclosure via purpose-bound, employee-authorised, short-lived verification request, recording the data categories, recipient, timestamp, expiry, revocation and source freshness. Never export disciplinary, medical, complaints or protected inclusion data as underwriting evidence.
5. Signed server-to-server tokens or a properly configured OIDC client with issuer, audience, expiry, signature, key rotation and revocation validation. Do not expose service credentials to Flutter or Next.js browser code.

## Existing routes and coexistence

- Financial Space operations are already routed through `apps/api/routes/financial_space_operations.php` with `auth:sanctum`. Do not equate ordinary authenticated access with action-specific Business Space authority.
- Payroll deduction already has a separate governed route and operational workflow in `apps/api/routes/payroll_deduction.php`. OpFin People must not create parallel credit or repayment calculations.
- OpFin Flutter navigation is implementation-dependent; keep existing journeys intact and introduce employee self-service as a gated entry in More once backend contracts are tested.

## Mandatory acceptance gates

- A person belonging to Business Space A cannot access Space B records; revoked membership loses access immediately or within a documented bounded session lifetime.
- Employers cannot inspect employees' private Personal Spaces, lending decisions or repayment histories.
- Disabled, missing, invalid, replayed and expired identity credentials fail closed; HR remains usable if OpFin is down.
- Consent withdrawal prevents new disclosures and is audited without retroactively rewriting already lawful financial records.
- Run the repository's existing API, Web and Flutter CI plus integration-specific contract tests and documentation-drift checks before production claims.

## Delivery boundaries

This contract intentionally makes **no claim** that an OIDC identity provider, service-to-service membership endpoint, live employer linking, consent-authorised HR verification or employee Flutter HR screen is currently implemented. Those require source-backed implementation and tests. Do not activate unapproved Railway resources as part of this work.
