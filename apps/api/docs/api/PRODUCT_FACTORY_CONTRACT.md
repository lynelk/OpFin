# Financing Product Factory contract

Status: Current source contract (foundation slice; not production-activated)  
Reviewed: 5 October 2026  
Language: English (United Kingdom)  
Requirements: PF-001, with DEV-003 configuration bounds. Register rows OPF-FEAT-0013 and OPF-FEAT-0014.

The Product Factory lets platform staff configure versioned `FinancialProduct` records for partners from approved templates. It decides which products may reach customer matching. It never prices an individual customer, moves money or posts to the ledger.

It is separate from the generic V5 product definitions at `POST /api/admin/product-factory/products`, which keep their existing contract. The financing factory lives under `/api/admin/financing-factory`.

## Roles

| Step | Who |
|---|---|
| Draft or approve a template | Draft: platform admin. Approve: a different platform admin |
| Draft a Legal Product Passport | Platform admin or operations |
| Approve or revoke a passport | Platform admin; approval needs a different person from the drafter |
| Draft, update, submit or revise a product | Platform admin or operations |
| Approve a product | A platform admin who is neither its maker nor its submitter |
| Reject, activate or retire a product | Platform admin |

Every create call needs an `Idempotency-Key` header (up to 160 characters). Repeating a key returns the original record. Reusing it for a different record returns 409. Every step is audited under `product_factory.*`.

## Templates

A template fixes the family, rail, allowed contract types and the guardrails a product may not exceed. Approved templates do not change: a new definition with the same `code` becomes the next version.

| Rail | Contract types | Price field |
|---|---|---|
| `CONVENTIONAL` | `CREDIT`, `HIRE_PURCHASE`, `FINANCE_LEASE` | APR in basis points |
| `ISLAMIC` | `MURABAHA`, `IJARA`, `MUSHARAKA_MUTANAQISA` | Profit rate in basis points |

Families are `asset_finance`, `device_finance`, `vehicle_finance`, `productive_asset_finance` and `working_capital`.

Guardrails (whole numbers):

- `tenor_months` `{min, max}`, between 1 and 360;
- `price_max_bps`, from 0 to 100000;
- `deposit_min_bps`, `ltv_max_bps` and `fee_max_bps` (optional), each 0–10000;
- `fee_types`, from `arrangement`, `late_payment`, `early_settlement` and `insurance_pass_through`;
- `asset_classes`, from the [asset registry](ASSET_REGISTRY_CONTRACT.md) classes. Required for the asset families;
- `required_disclosures`, at least one of `total_cost`, `late_payment_consequences`, `complaints_contact`, `cooling_off` and `ownership_and_repossession`.

## Legal Product Passports

A passport records the regulated activity, booking entity, optional partner, funder and servicer. Approval requires a licence or approval reference and an evidence reference. `effective_from` defaults to the approval time. Revocation takes effect at once and removes every product that depends on the passport from matching.

## Products

A product names its template, contract type, passport, parameters, disclosures, and the actual `lender_reference`, `funder_reference` and `principal_reference`. All three parties are required. The factory refuses a product that leaves the template's bounds:

- tenor range inside the template's range;
- `price_bps` no higher than the cap;
- `deposit_bps` at least the minimum, `ltv_bps` no higher than the cap;
- each fee of an allowed type and no higher than `fee_max_bps`;
- asset classes from the template's list;
- every required disclosure filled in.

Errors are returned as one plain-language message (HTTP 422). Islamic templates speak of a profit rate, never an APR.

Lifecycle: `draft` → `submitted` → `approved` → `live` → `retired`. A rejection returns a submitted product to `draft`. Only drafts can be edited. To change an approved or live product, `revise` it: this creates the next version as a draft, with `supersedes_product_id` pointing at its source. The source stays exactly as approved.

Activation checks, at the time of activation:

1. the template is still active;
2. the passport is approved, not revoked, effective now, in the product's jurisdiction and, if it names a partner, for the same partner;
3. an Islamic product carries an approved, current Sharia approval. Sharia approvals come only from the governance record (`sharia_approvals`); the factory cannot create, approve or override one. A conventional product cannot carry one.

Activating a version retires the previous live version of the same code. Customer matching (`POST /api/product-matches`) still applies its own checks: live, effective, approved passport, and for `SHARIA_ONLY` intents an approved Sharia approval.

## Endpoints

All under `/api/admin/financing-factory`, for `platform_admin` and `operations` (the service enforces the narrower roles above).

| Method | Path | Purpose |
|---|---|---|
| GET | `/templates` | List templates |
| POST | `/templates` | Draft a template (or its next version) |
| POST | `/templates/{template}/approve` | Approve a draft template |
| POST | `/templates/{template}/retire` | Retire a template; its products can no longer be activated |
| GET | `/passports` | List Legal Product Passports |
| POST | `/passports` | Draft a passport |
| POST | `/passports/{passport}/approve` | Approve with licence and evidence references |
| POST | `/passports/{passport}/revoke` | Revoke with a reason |
| GET | `/products` | List factory products and versions |
| POST | `/products` | Draft a product (version 1) |
| PUT | `/products/{product}` | Change a draft |
| POST | `/products/{product}/submit` | Submit for approval |
| POST | `/products/{product}/approve` | Approve (second person) |
| POST | `/products/{product}/reject` | Return to draft with a reason |
| POST | `/products/{product}/activate` | Put live after the activation checks |
| POST | `/products/{product}/retire` | Retire a live product |
| POST | `/products/{product}/revise` | Create the next draft version |

## Not in this slice

Customer pricing and schedules, partner APIs and webhooks (PF-002), supplier settlement, App and Web screens, and accounting set-up. A live product here is a configuration record; production activation still needs the gates in the [integrated delivery plan](../../../../docs/development/OPFIN_INTEGRATED_DELIVERY_PLAN_2026-09-26.md).
