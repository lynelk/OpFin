# Financial Spaces Domain Model

Status: Canonical target domain model  
Updated: 24 September 2026

## Aggregate model

```text
Person
 ├─ Identity / Verification / Consent
 ├─ Personal Space (exactly one active canonical Personal Space)
 └─ Memberships
      └─ Financial Space
           ├─ type
           ├─ organisation (optional)
           ├─ capabilities
           ├─ entitlements
           ├─ memberships / roles
           ├─ external credentials
           └─ financial records
```

## New canonical entities

### financial_spaces
Represents the ownership/context boundary for financial records.

Minimum fields: id, public_id, type, name, country, currency, organisation/institution reference where applicable, status, metadata, timestamps.

`public_id` is the stable OpFin identity for the Space. Authority-issued identifiers never replace it. Current group-oriented types include `savings_group`, `investment_club` and `sacco` alongside Personal, Household, Business and institutional contexts.

### financial_space_memberships
Links a Person/User to a Space. Minimum fields: space, user, role, status, joined/approved/suspended dates, metadata. A user may have many memberships.

### financial_space_invitations
Invitation-first acquisition for groups, employers, SACCOs and organisations. Store recipient contact, intended role, token hash, expiry, acceptance state and inviter.

### financial_space_credentials
Stores external government, regulator, cooperative, tax or other authority identifiers attached to a Space. Minimum fields: space, credential type, issuer code/name, value, jurisdiction, verification status, issue/expiry dates, verifier and verification evidence/reference.

Credential lifecycle is independent of the Space lifecycle. A group can exist before registration, add a newly introduced government code later and retain the same OpFin history. Verification does not create a second group or grant access to members' Personal Spaces.

### capabilities
Stable capability keys such as budgeting, goals, debt_management, group_savings, member_loans, investments, insurance, employer_services and partner_distribution.

### financial_space_capabilities
Declares whether a capability is enabled/configured for a Space. Capability is not permission.

### plans / entitlements
Commercial/product access. Entitlement answers whether a plan enables a feature; it must remain separate from permission and regulatory eligibility.

### partner_catalogue
Partner, product, territory, eligibility, pricing/disclosures, integration adapter, lifecycle and commercial-agreement references.

### revenue_events
Immutable commercial attribution for subscription, commission, revenue-share, transaction, API/platform and servicing economics. Financial settlement remains reconciled through the appropriate billing/payment/ledger boundary.

## Protection ownership and audience scope

Protection products distinguish `personal`, `group` or `both` audiences. Personal policies remain owned by the person. The schema permits a future policy to reference a Financial Space and explicit coverage scope, but group enrolment and premium collection remain unavailable until the regulated partner, consent, custody/allocation and operating controls are activated.

Catalogue visibility is not financial activation.

## Three independent gates

An action is available only when all applicable gates pass:

1. **Permission:** is this actor authorised in this Space?
2. **Entitlement:** is the capability included/enabled for this Space/plan?
3. **Eligibility:** may this customer/entity use the product under product, risk and regulatory rules?

Do not encode these three concepts into one role or boolean.

## Migration compatibility

Current `user_id`-owned financial-wellbeing records remain valid during transition. Add nullable `financial_space_id`, backfill each user's canonical Personal Space, then enforce Space ownership only after parity tests pass.

Current `community_finance_programmes` and `community_finance_memberships` are reusable domain evidence, but should converge on Financial Spaces/memberships rather than becoming a parallel identity/organisation universe.

Current `institutions` remain the legal/institutional record where applicable. A Financial Space references an institution rather than duplicating it.

## Privacy boundary

Membership never implies cross-Space financial visibility. Employer administrators cannot see an employee's Personal Space. Group officials see only records authorised within the Group Space. Consolidated views are projections created for the requesting person under explicit permissions/consent; they do not merge ownership.
