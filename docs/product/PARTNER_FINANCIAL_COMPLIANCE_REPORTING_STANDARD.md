# OpFin Partner Financial, Compliance and Service Reporting Standard

Status: Canonical product and integration contract  
Version: 1.0  
Effective: 22 September 2026  
Applies to: OpFin, Stolets integrations, Cito/CPay integrations, employers, CRBs/KYC providers, banks/MNOs, funding partners, insurers, savings/investment partners and future third-party services

## 1. Purpose

This standard defines the financial, operational, risk and compliance evidence required for OpFin to operate as an independent financial platform while safely using ecosystem and third-party services.

It is an implementation contract, not a claim that every external provider has already supplied production credentials, completed certification or activated every report.

## 2. Product independence

Every Core Synergies product must remain independently useful and independently operable.

- OpFin owns its customer/product state, consent, decisioning, financial profile, product obligations, ledger, servicing, reconciliation and regulatory evidence.
- Stolets remains a separate SME automation, digitisation and commerce platform.
- Cito is the preferred third-party service integration gateway.
- CPay is the preferred payment capability/route.
- Cito/CPay must not become a mandatory runtime dependency for OpFin.
- Every external service should support a clean direct-provider adapter as a controlled backup when a real provider contract permits it.
- Direct fallback must preserve the same consent, purpose, security, audit, evidence, commercial attribution and reconciliation rules.
- No silent failover is permitted after an ambiguous paid/provider request. The original operation must be reconciled first.

The target pattern is:

```text
OpFin -> Cito -> configured provider        (preferred)
OpFin --------> certified direct provider  (controlled backup)
```

The same pattern applies to payments, identity/KYC/KYB, NIN validation, CRB/credit information, communications, account data, insurance, savings/investment services and future provider capabilities.

## 3. Universal provenance rule

Every externally sourced value used by OpFin must retain:

- value or normalised result;
- source/provider;
- provider reference;
- route used;
- request/correlation reference;
- source timestamp;
- received timestamp;
- consent reference;
- permitted purpose;
- environment;
- schema/model version where applicable;
- confidence/status;
- expiry where applicable;
- evidence/audit reference.

A final score or decision must never destroy the provenance needed to explain it.

## 4. Stolets Financial Passport

Stolets should provide a consented, standardised Financial Passport rather than expose unrestricted raw merchant data.

Minimum report families:

| Report | Minimum content | Typical cadence |
| --- | --- | --- |
| Business Identity & KYB | Legal/trading identity, registration/TIN where applicable, owners/controllers, outlets, operating history | Onboarding + change |
| Sales Performance | Gross/net sales, returns, discounts, transaction count, average ticket, trading days | Daily/monthly |
| Cash-flow | Recorded inflows/outflows, operating cash generation, payment-channel mix | Daily/monthly |
| Profitability | Revenue, COGS, gross margin, operating expenses, operating-profit proxy | Monthly |
| Inventory | Stock value, purchases, turnover, stock-outs, shrinkage/dead stock | Daily/monthly |
| Supplier | Purchase volume, concentration and payment behaviour | Monthly |
| Receivables | Credit sales, outstanding balance, ageing and collections | Daily/monthly |
| Payables | Supplier obligations, ageing and overdue position | Monthly |
| Settlement | Cash/payment-channel settlement against recorded sales | Daily |
| Fiscal/tax reconciliation | Applicable fiscalised sales/receipt reconciliation | Daily/monthly |
| Business continuity | Trading-day consistency, closure periods, outlet/device activity | Monthly |
| Anomaly/fraud | Voids, refunds, reversals, duplication and unusual offline/sync behaviour | Event/daily |
| Data quality | Coverage, missing periods, sync health, source-device coverage | Every passport |

OpFin should use derived, consented indicators needed for affordability/risk rather than indiscriminately ingesting the merchant's full operating database.

Stolets must remain fully functional without OpFin.

## 5. Payment and money-movement reporting

For CPay or any certified direct payment adapter, OpFin requires:

- transaction ledger;
- provider and customer reference;
- gross amount and currency;
- fees/taxes;
- settlement amount/date/account;
- reconciliation status;
- pending/failed operations;
- reversals/refunds;
- disputes/chargebacks where applicable;
- provider SLA/performance;
- fraud/risk exceptions;
- source route and environment.

Provider acknowledgement is not financial finality. OpFin's product and accounting state remains authoritative only after the governed provider-finality transition and reconciliation.

## 6. CRB, KYC and identity-provider reporting

Required report/evidence families include:

- identity/NIN verification result;
- CRB enquiry;
- CRB score/attributes;
- current credit exposure;
- credit-history/delinquency evidence;
- enquiry history where available and permitted;
- CRB furnishing/submission;
- rejected furnishing records;
- dispute/correction lifecycle;
- consent evidence;
- provider/data-quality status.

gnuGrid is consumed through Cito's provider-neutral identity/risk fabric as the preferred route. OpFin retains a direct provider route as controlled backup.

## 7. Employment and payroll reporting

### 7.1 Core employment/affordability verification

Employers/payroll providers may supply, with appropriate consent:

- active employment status;
- verified salary/net pay;
- payroll cycle;
- salary-payment history;
- existing payroll deductions;
- available deduction headroom;
- employment start date;
- contract type/end date where relevant;
- deduction/remittance results;
- exit/contract-end information where lawfully and contractually reportable.

### 7.2 Positive-Only Employment Behaviour Enrichment

Positive verified employment behaviour may provide a small, capped credit benefit.

Permitted positive examples may include:

- attendance reliability;
- verified positive performance outcome;
- employment progression;
- formal recognition;
- verified workplace reliability.

Rules:

1. Positive evidence may create an uplift.
2. Missing information is neutral.
3. Employer non-participation is neutral.
4. Customer refusal of optional behaviour enrichment is neutral.
5. Negative/inconclusive behaviour information does not reduce the score under this enrichment policy.
6. The uplift is capped and separately explainable.
7. The base composite score and data coverage remain visible.
8. Every applied signal must be verified, risk-eligible and linked to active credit-processing consent.

The current default maximum uplift is configured through `OPFIN_EMPLOYMENT_BEHAVIOUR_MAX_UPLIFT`.

### 7.3 Payroll deduction/remittance report

For payroll-linked loans, the employer report must distinguish:

```text
employee -> loan -> amount due -> amount deducted -> amount remitted
-> remittance date -> difference -> exception reason
```

A borrower non-payment and an employer deduction-without-remittance are different events and must never be conflated.

## 8. Financial Account Behaviour Report

Banks, MNOs and account-data providers should expose a standard account-behaviour report, subject to consent, covering:

- verified inflows;
- recurring income;
- income stability;
- transaction/activity frequency;
- account/wallet tenure;
- balance trajectory;
- cash-flow volatility;
- failed/returned payments;
- visible recurring deductions/obligations;
- confirmed OpFin disbursement/repayment transactions;
- unusual activity indicators;
- source coverage and confidence.

OpFin should ordinarily consume normalised/derived indicators. Raw transaction histories should be retained only when genuinely necessary and lawfully permitted.

## 9. Capital & Loan Book Performance Report

Every funding partner/private capital pool must receive a report that can be reconciled to the underlying loan and payment records.

Minimum sections:

### Capital
- opening/available capital where available;
- capital additions/withdrawals;
- committed capital;
- deployed capital;
- unused capacity.

### Origination
- applications;
- approvals/declines where in scope;
- loans originated;
- principal disbursed;
- average ticket/tenure where applicable.

### Portfolio performance
- principal outstanding;
- collections;
- current loans;
- PAR1/7/30/60/90 where modelled;
- NPLs;
- restructures;
- write-offs;
- recoveries;
- vintage/cohort performance.

### Economics
- borrower interest;
- borrower fees;
- servicing/platform fees;
- provider/payment costs;
- taxes;
- partner revenue share;
- net funder return where contractually defined.

### Funding provenance
Every newly funded production loan must be attributable to one contractual funding pool. Unassigned funding provenance is a reportable control exception.

OpFin currently uses `loans.funding_pool_id` to preserve this link without rewriting historical loans.

## 10. Insurance Product, Premium & Claims Report

Minimum sections:

- policies enrolled/issued/active/lapsed/cancelled;
- gross customer premium;
- insurer settlement;
- premium pending settlement;
- failed/refunded premium;
- customer-funded platform/service fee;
- insurer-funded commission;
- Cito fee where applicable;
- OpFin fee where applicable;
- taxes/levies;
- claims submitted;
- claims approved/declined/paid/disputed/outstanding;
- claimed/approved amounts;
- claims turnaround where available;
- complaints/disputes;
- reconciliation exceptions.

Premium principal is not platform revenue. The service-economics record keeps premium/settlement evidence separate from fees and commissions.

## 11. Savings & Investment Partner Report

Minimum sections:

### Savings
- contributions;
- withdrawals;
- current partner-confirmed custody;
- pending partner confirmation;
- failed/reversed movements;
- customer/service/platform fees;
- partner settlement;
- reconciliation exceptions.

### Investments
- subscriptions/contributions;
- holdings/units where supplied;
- NAV/value where supplied;
- redemptions/withdrawals;
- investment income/returns where supplied;
- fees/taxes;
- partner settlement;
- complaints/disputes;
- participatory-finance commitments and settlement where applicable.

Savings principal and investment capital are not platform revenue.

## 12. Universal Service Economics & Revenue Report

Every paid or commercially relevant external service must use one consistent economics vocabulary.

Minimum event fields:

```text
service_code
capability_code
provider
route
environment
request_reference
provider_reference
status
currency

provider_gross_cost_minor
provider_discount_minor
provider_net_cost_minor

customer_service_charge_minor
customer_platform_fee_minor
partner_commission_minor
cito_platform_fee_minor
opfin_platform_fee_minor
tax_amount_minor

net_settlement_to_provider_minor
gross_revenue_minor
net_revenue_minor
gross_margin_minor

price_book_version
contract_version
reconciliation_reference
occurred_at
reconciled_at
```

Semantics:

- known zero = `0`;
- unknown/not supplied = `null`;
- principal/premium/capital movement is stored as transaction metadata or settlement evidence and is not automatically treated as revenue;
- commercial values may be enriched during reconciliation;
- one request/service reference remains idempotent;
- reports expose incomplete commercial fields rather than silently guessing them.

This applies to:

- payments;
- CRB/credit-data enquiries;
- KYC/KYB/NIN validation;
- SMS/WhatsApp/email;
- insurance;
- savings;
- investments;
- account aggregation;
- collections/recovery services;
- any future third-party service.

## 13. Service economics report

The report must be filterable by:

- period;
- service;
- capability;
- provider;
- route;
- environment;
- partner/product where available.

It must show:

- total events;
- successful/failed/pending events;
- provider cost;
- customer service charges;
- customer platform fees;
- partner commissions;
- Cito fees;
- OpFin fees;
- taxes;
- provider settlement;
- net revenue;
- gross margin;
- unknown provider-cost count;
- incomplete revenue-event count.

Every aggregated amount must be traceable to its underlying service-economics event and, where applicable, revenue event, payment, invoice, settlement and ledger evidence.

## 14. Reporting cadence

| Cadence | Typical evidence |
| --- | --- |
| Real time/event | KYC, consent, provider request/result, payment, disbursement, repayment, fraud/security event |
| Daily | Settlement, reconciliation, provider exceptions, Stolets sales/cash-flow summaries |
| Monthly | Financial Passport, loan book, payroll, profitability, partner economics |
| Quarterly | Partner risk, compliance, security/BCP and performance review |
| Annual | Audit, licence/certification, privacy/AML/regulatory reporting evidence as applicable |
| Immediate/ad hoc | Material incident, breach, fraud, major outage or regulator request |

The underlying event/ledger record is authoritative. A report is a reproducible projection, not the primary financial record.

## 15. Current OpFin API reporting surfaces

Operations/admin routes:

- `POST /api/admin/service-economics-events`
- `GET /api/admin/reports/service-economics`
- `GET /api/admin/reports/capital-loan-book`
- `GET /api/admin/reports/insurance`
- `GET /api/admin/reports/savings-investments`
- `GET /api/admin/reports/employment-positive-behaviour`
- `GET /api/admin/reports/financial-account-behaviour`

Report requests accept `from` and `to` dates. Service-economics reporting additionally supports service/provider/route/environment filters.

## 16. Operational activation boundary

The codebase may provide adapter seams, reports and evidence models before a provider is activated. Production activation still requires genuine:

- credentials;
- provider/API contract;
- commercial terms;
- licence/authorisation where applicable;
- security review;
- data-protection terms;
- sandbox/provider certification;
- reconciliation evidence;
- rollback/fallback readiness.

The repository must fail closed rather than invent credentials, provider capabilities, fees or regulatory approval.
