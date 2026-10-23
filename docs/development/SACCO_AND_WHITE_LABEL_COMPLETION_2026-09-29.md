# SACCO and white-label completion programme

Status: Source merged through PR #147; regulated activation and complete product acceptance remain evidence-driven.  
Reviewed: 21 October 2026

## Benchmark used

Current East African SACCO products consistently treat member/KYC, shares, multiple savings/deposit products, guarantor-aware loans, dividends/interest, double-entry accounting, mobile-money collection/disbursement with reconciliation, multi-level approvals, member self-service, statutory reporting, audit history and portfolio analytics as baseline core-banking capabilities. OpFin adopts those capability classes without copying a vendor implementation.

## OpFin SACCO target

The SACCO Financial Space is the tenant boundary and member operating context.

Core:
- member/KYC and lifecycle;
- configurable share, mandatory/voluntary savings, fixed-deposit, welfare and loan products;
- member subaccounts and historical positions;
- share register and paid-up capital;
- contribution/collection/withdrawal/disbursement action intents;
- guarantor requests, acceptance, held exposure and release;
- loan origination, schedules, arrears, restructuring, write-off/recovery and provisioning through governed lending rails;
- dividends on shares and interest on deposits as separate approved runs;
- double-entry general ledger, period close and reconciliation;
- budgets, expenses, payables/receivables and cash-flow;
- branches/cost centres/tellers and scoped roles;
- statements, audit trail and immutable historical reporting;
- member, liquidity, growth, portfolio, PAR/NPL, concentration and profitability analytics;
- Cito capabilities/communications where certified and CPay money movement/reconciliation;
- imports and migration controls;
- API/webhook access.

## White label

SACCO and Investment Club Spaces can define a brand profile and request one or more custom hostnames. A hostname remains pending until domain control is proven. TLS/proxy provisioning and DNS verification are deployment responsibilities; the application never activates an arbitrary claimed hostname.

The same OpFin identity can participate in multiple branded Spaces. A branded hostname changes presentation and initial Space context, not identity ownership, ledger tenancy, permissions or personal-data boundaries.

White-label configuration may set display name, short name, logo URL, primary/accent colours and support contacts. Regulated disclosures must continue to identify the actual SACCO, lender, insurer, custodian or investment provider. White labelling must never disguise the legal provider of a financial product.

## Activation

SACCO Core is enabled in application defaults on this implementation branch. This is product activation, not blanket legal authorisation. Each SACCO still requires its own organisation/KYB/regulatory evidence, product configuration, custody/settlement arrangement, commercial pricing and production acceptance before regulated live operations.

## Remaining engineering

- post member-account/share movements into a SACCO-specific immutable double-entry subledger;
- dividend/interest run calculator, preview, checker approval, posting and CPay distribution;
- guarantor acceptance/hold/release lifecycle bound to loan decisions;
- branch/cost-centre/teller schema;
- SACCO loan-product adapter to existing lender orchestration and funding controls;
- provider-finality callbacks into Space action state and accounting;
- Web Workspace and Flutter member/officer journeys;
- host resolution in server layout and branded login/onboarding screens;
- verified DNS/TLS domain activation automation;
- member and management analytics dashboards;
- Uganda regulatory report pack configured from approved filing schemas;
- PostgreSQL, browser, device, accessibility, security and financial acceptance.

## Current source versus regulated readiness (21 October 2026)

The current repository registers SACCO products, member positions, guarantees, distribution approvals and Financial Space brand/domain requests in `apps/api/routes/sacco_core.php`. This does not certify a particular SACCO, its legal authority, deposit-taking custody, share/savings/loan accounting, DNS/TLS hostname control or a production payout. Complete double-entry subledger, settlement reconciliation, branch/teller, member and institutional UAT, and verified host deployment must be assessed independently. See [source-to-release register](../operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md) and [role-based training](../manuals/ROLE_BASED_TRAINING_PATHS.md).

## Candidate SACCO regulatory and settlement routes (23 October 2026)

The Core Release adds `/api/financial-spaces/{space}/sacco/regulatory-attestations` (GET and POST), `/products/{product}/activate` (POST), `/accounts/{account}/allocate-settlement` (POST) and platform administrator/operations `/api/admin/sacco/{space}/regulatory-attestations/{attestation}/review` (POST). Submissions are evidence records pending independent verification; they are **not** a Uganda regulator confirmation. Product activation must require a current matching approved legal attestation, authorised maker/checker, product and lender scope and independently verified funding or deposit-taking arrangements where relevant. Settlement allocation must identify a unique externally confirmed source receipt, exact account and amount, reject conflicting allocations, and preserve immutable accounting reversal evidence.

**Operational hold:** no SACCO live deposits, lending, payouts or licensing representation may be inferred from route availability or a synthetic attestation. Independent country/product/custody/legal and production finance acceptance remains outstanding.
