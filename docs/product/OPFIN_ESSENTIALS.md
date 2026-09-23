# OpFin Essentials

Updated: 23 September 2026  
Status: implemented product capability; live provider activation remains configuration- and contract-gated.

## 1. Product position

**OpFin remains a financial operating platform with embedded financial services.** Essentials is one capability within OpFin. It does not redefine OpFin as a utility lender, rent lender or lending-only application.

OpFin Essentials helps a customer keep verified household or business essentials running when a payment timing gap exists. It combines the customer's existing OpFin financial picture, responsible-credit headroom, consented eligibility data, approved third-party lenders and purpose-bound provider settlement.

OpFin is **not the primary lender** for Essentials. Every offer must identify the third-party lender that supplies the credit. OpFin acts as the customer experience, orchestration, servicing, controls, reconciliation and partner-integration layer.

## 2. What can be financed

| Category | Initial examples | Settlement rule |
| --- | --- | --- |
| Electricity | UEDCL | Verified account/meter; provider payment |
| Water | NWSC | Verified account; provider payment |
| Internet | Approved fixed/mobile internet billers | Verified subscriber account; provider payment |
| Television | DStv, GOtv, StarTimes and approved billers | Verified subscriber account; provider payment |
| Household energy | Approved LPG/energy providers | Verified provider/customer reference |
| Rent | Verified landlord/property beneficiary | Manual beneficiary verification then purpose-bound beneficiary payment |

Additional categories can be added through the governed biller catalogue without changing the credit domain.

Cash-out is not an Essentials fulfilment route. The financed amount goes to the verified provider or beneficiary.

## 3. Customer proposition

The customer can:

1. add an essential-service account or rental beneficiary;
2. verify the provider account, or wait for manual rental-beneficiary review;
3. refresh eligibility across approved participating lenders;
4. request financing for a specific verified essential;
5. review the lender, provider payment, interest, fees, total repayment and term;
6. accept or reject the offer;
7. receive provider-payment confirmation and any provider fulfilment evidence;
8. repay through a verified repayment wallet/phone;
9. see the resulting obligation inside the relevant Financial Space;
10. control and revoke permissions granted to Stolets or another embedded platform.

Ordinary OpFin borrowing, savings, growth, protection, employer and Financial Compass journeys remain separate and intact.

## 4. One responsible-credit headroom

A customer can be eligible with more than one lender, but lender limits are **not added together**.

Essentials availability is constrained by the customer's overall OpFin responsible-credit headroom and the largest eligible lender line that can serve the requested purpose. Existing active Essentials exposure reduces that headroom.

This prevents a customer from turning three UGX 200,000 lender approvals into UGX 600,000 of simultaneous exposure merely because multiple providers exist.

## 5. Lender model

Essentials starts with third-party lending.

A lender is represented by an approved partner, an active partner product, regulatory/licensing evidence, eligibility rules, pricing and term rules, supported Essentials categories, and one approved decision/funding route.

### 5.1 Capital-mandate route

The lender has an approved, active capital mandate linked to the lender partner.

OpFin may reserve capital for an accepted quote, but cannot deploy more than unreserved committed capital. On confirmed provider settlement, reserved capital becomes deployed capital. Confirmed principal repayment restores lender capital capacity.

### 5.2 Cito-managed lender route

A participating lender can expose an eligibility/credit-line capability through Cito. OpFin sends the lender/product reference, the relevant OpFin credit-profile snapshot and a valid credit-processing consent reference. The third party remains the lender.

### 5.3 OpFin lender prohibition

The operations API rejects a lender configuration whose lender code or name identifies OpFin. This is deliberate. Changing the business model later requires an explicit product, legal, regulatory and accounting decision rather than a configuration accident.

## 6. Data and external services

### 6.1 Cito

Cito is the preferred external-service gateway.

**All gnuGrid services used by OpFin must be accessed through Cito.** Direct CRB or identity configuration identified as gnuGrid is blocked. This applies beyond Essentials as a platform-wide integration rule.

Cito can also expose third-party lender decisioning for an Essentials product.

### 6.2 CPay

CPay is the preferred money-movement and settlement route.

The implemented Essentials contract supports separate configuration for service-account lookup, utility/biller payment, verified-beneficiary payment including rent, lender repayment collection, and transaction-status reconciliation.

A customer repayment identifies the verified repayment wallet/phone. CPay moves and reconciles money; OpFin remains authoritative for contractual allocation between non-principal amounts and principal.

### 6.3 External activation gates

Code completeness does not imply that a provider route is commercially live. Production activation requires the corresponding certified CPay/Cito route, provider credentials, commercial agreement, reconciliation acceptance and operating ownership.

The API fails closed when a required provider path is not configured.

## 7. Financial Spaces

Every Essentials account, lender line, quote and advance is linked to the appropriate Financial Space.

When provider settlement succeeds, OpFin creates a financial obligation in that space. The obligation records the third-party lender as counterparty and the total customer repayment obligation. Confirmed repayments reduce the obligation; full repayment settles it.

This allows Essentials to improve the customer's overall financial picture rather than creating an isolated loan ledger beside it.

## 8. Rental finance

Rent is built into the domain from the start.

Rental finance requires a tenancy/landlord or property reference, landlord/property-manager name, payment-beneficiary name, beneficiary payment channel, and operations verification before financing.

The customer does not receive rental cash. Following verification and acceptance, CPay uses the verified-beneficiary settlement route.

Rental limits, lender eligibility, pricing and permitted tenures remain product-configurable.

## 9. SME and embedded distribution

Stolets is an intended SME distribution channel, but the architecture is provider-neutral.

Any approved platform can use the partner API after completing OpFin platform due diligence, receiving an active partner distribution account, being authorised for the Essentials product, and obtaining a customer-granted, Financial-Space-scoped Essentials authorisation.

Supported customer-controlled scopes are eligibility, account_write, quote_create and status_read.

A platform cannot create debt for a customer merely because it has API access. Embedded completion requires a separate, short-lived customer authorisation token after the customer has seen the offer.

Stolets remains a separate SME automation/digitisation product. It may originate an Essentials journey or provide consented business context; OpFin remains the financial-services domain.

## 10. Offer selection and commercial model

Among eligible lender lines capable of serving the category and channel, the current orchestration selects the valid offer with the **lowest total customer repayment**. OpFin revenue does not determine the selected lender.

Possible commercial revenue sources include disclosed lender-funded origination or servicing revenue, biller/provider transaction commissions where contracted, embedded-platform commercial arrangements, lender/platform API servicing fees, and other contracted service revenue captured through service-economics events.

Customer interest and customer fees belong to the configured lender product and must comply with applicable lending and distribution rules. OpFin's platform revenue is recorded separately so economics can be audited without disguising it as customer pricing.

## 11. Android / store-distributed credit controls

For store-distributed personal-credit journeys, the current OpFin policy requires a full-repayment term of at least 61 days and prefers eligible 90-day-plus structures. The Android Essentials quote engine rejects lender products that do not satisfy the store-channel minimum.

Product/legal classification remains authoritative. A short loan must not be relabelled as another product merely to evade a platform rule.

## 12. Core states

Account verification: pending, pending_manual_review, verified, failed.

Credit line: active, or inactive/expired through status and expiry controls.

Quote: offered, accepted, expired.

Advance: funding_reserved, fulfilment_pending, active, overdue, settled, fulfilment_failed.

Repayment: pending, pending_provider_confirmation, successful.

## 13. Security and control requirements

Essentials preserves the following controls:

- no customer cash-out;
- verified provider/beneficiary before finance;
- named lender in disclosures;
- immutable disclosure hash at acceptance;
- explicit customer confirmation for debt creation;
- one overall responsible-credit headroom;
- atomic lender-capital reservation/deployment;
- idempotent repayments;
- encrypted service-account references at rest;
- account references masked in normal API responses;
- customer-controlled embedded-platform permissions;
- provider acknowledgement is not treated as financial finality unless the configured response is a confirmed final state;
- reconciliation route for pending fulfilment;
- audit events for account, offer, advance, repayment and partner-permission actions;
- financial obligation kept in sync with repayment;
- sensitive provider tokens redacted from stored fulfilment evidence.

## 14. API map

Customer:

- GET /api/essentials
- GET /api/essentials/catalogue
- POST /api/essentials/accounts
- POST /api/essentials/accounts/{account}/verify
- POST /api/essentials/eligibility
- GET /api/essentials/quotes
- POST /api/essentials/quotes
- POST /api/essentials/quotes/{quote}/authorise-partner
- POST /api/essentials/quotes/{quote}/accept
- GET /api/essentials/advances
- POST /api/essentials/advances/{advance}/repay
- GET /api/essentials/partner-authorisations
- POST /api/essentials/partner-authorisations
- DELETE /api/essentials/partner-authorisations/{authorisation}

Embedded platforms:

- POST /api/partner/essentials/customers/{customer}/eligibility
- POST /api/partner/essentials/customers/{customer}/accounts
- POST /api/partner/essentials/customers/{customer}/quotes
- POST /api/partner/essentials/quotes/{quote}/complete

Operations:

- GET /api/admin/essentials/portfolio
- GET /api/admin/essentials/work-queue
- POST /api/admin/essentials/billers
- PATCH /api/admin/essentials/billers/{biller}
- POST /api/admin/essentials/accounts/{account}/verify
- POST /api/admin/essentials/lenders
- POST /api/admin/essentials/advances/{advance}/reconcile

## 15. Production acceptance

Essentials is ready for production activation only when all applicable gates are satisfied:

- at least one non-OpFin lender has current regulatory evidence;
- lender product and pricing are approved;
- lender capital mandate or Cito-managed lender route is active;
- CPay lookup/payment/repayment/reconciliation routes are certified;
- verified-beneficiary settlement is certified before rental activation;
- required service-provider/biller contracts are signed;
- customer disclosures and legal documents are approved;
- repayment and fulfilment reconciliation exercises pass;
- operations owners are assigned for verification and exceptions;
- Android/store policy review passes for the configured product terms;
- monitoring, complaints and audit reporting are enabled.

Until those external gates are satisfied, the software remains implemented but the affected provider route must remain disabled.
