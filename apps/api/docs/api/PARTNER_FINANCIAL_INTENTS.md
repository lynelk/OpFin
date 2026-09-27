# Partner financial intent handoff

Status: implementation candidate  
Reviewed: 27 September 2026  
Language: English (United Kingdom)

## Purpose

Stolets, Shamba and CoreWorks can pass a customer's financing need into OpFin without creating a loan, selecting a lender or accepting an offer on the customer's behalf.

The partner creates a referral request. OpFin then requires the customer to confirm that request inside an active Financial Space. Only after confirmation does OpFin create the canonical `FinancialIntent`; normal product matching, application, decision, disclosure and acceptance controls continue from there.

## Partner request

`POST /api/partner/financial-intents/{customer}`

Required controls:

- authenticated `partner_api`, `platform_admin` or `operations` role;
- an active/approved partner distribution account owned by the caller unless the caller is a platform administrator or operations user;
- the partner distribution account must explicitly allow `finance`, `credit` or `financial_intents`;
- `source_platform` is currently one of `stolets`, `shamba` or `coreworks`;
- `Idempotency-Key` is mandatory;
- `external_reference` is unique per partner account;
- a `customer_consent_reference` from the originating platform is required;
- the referral expires after seven days if the customer does not confirm it.

The partner response exposes referral state only. It does not return the customer's Financial Space membership, credit profile, product matches or lender decision.

## Customer control

Authenticated OpFin customers use:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/partner-financial-intents` | List referrals addressed to the signed-in customer |
| POST | `/api/partner-financial-intents/{request}/confirm` | Confirm a referral into a customer-authorised Financial Space and create the canonical `FinancialIntent` |
| POST | `/api/partner-financial-intents/{request}/decline` | Decline a pending referral without creating a financial intent |

Confirmation requires `financial_space_id`. The customer may also choose `ALL_SUITABLE`, `SHARIA_ONLY` or `CONVENTIONAL_ONLY`; the originating platform cannot set that preference for the customer.

When confirmed, OpFin adds source attribution to the intent's purpose metadata using the source platform, partner account, partner request reference and external reference. That provenance must not be treated as underwriting evidence by itself.

## Boundary

A partner financial-intent request is not:

- a credit application;
- lender authority;
- affordability approval;
- a payroll deduction undertaking;
- an accepted loan offer; or
- a payment/disbursement instruction.

Those later actions remain under OpFin's normal customer, lender, regulatory and financial-control workflows.
