# Financial Spaces Domain Model

Status: Canonical target domain model  
Updated: 19 September 2026

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
           └─ financial records
```

## New canonical entities

### financial_spaces
Represents the ownership/context boundary for financial records.

Minimum fields: id, public_id, type, name, country, currency, organisation/institution reference where applicable, status, metadata, timestamps.

### financial_space_memberships
Links a Person/User to a Space. Minimum fields: space, user, role, status, joined/approved/suspended dates, metadata. A user may have many memberships.

### financial_space_invitations
Invitation-first acquisition for groups, employers, SACCOs and organisations. Store recipient contact, intended role, token hash, expiry, acceptance state and inviter.

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


## Essentials financing in Financial Spaces

Essentials does not create a parallel customer balance sheet. Each saved service account, lender credit line, quote and advance is scoped to a Financial Space.

On confirmed provider or beneficiary settlement, OpFin creates a linked `financial_obligations` record with the actual third-party lender as counterparty. Confirmed repayments reduce that obligation and full repayment settles it. The Essentials advance retains the obligation identifier so the customer financial picture and lender-servicing state cannot silently diverge.

The supporting domain tables are:

- `essentials_billers`
- `essentials_accounts`
- `essentials_credit_lines`
- `essentials_partner_authorisations`
- `essentials_quotes`
- `essentials_advances`
- `essentials_repayments`

Service-account references are encrypted at rest and masked in ordinary API responses. Embedded platforms receive only customer-authorised Financial-Space scopes. See `../product/OPFIN_ESSENTIALS.md`.
