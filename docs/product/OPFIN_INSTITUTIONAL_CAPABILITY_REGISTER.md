# OpFin Institutional Capability and Commercial Readiness Register

**Status:** Proposed B2B source-to-acceptance register; *not* production, provider, legal or financial launch approval  
**Prepared:** 24 October 2026 • English (UK)  
**Machine-readable companion:** [OPFIN_INSTITUTIONAL_CAPABILITY_REGISTER.json](OPFIN_INSTITUTIONAL_CAPABILITY_REGISTER.json)  
**Strategy:** [B2B Institutional Operating Blueprint](OPFIN_B2B_INSTITUTIONAL_OPERATING_BLUEPRINT.md)  
**Acceptance programme:** [B2B Institutional Acceptance](../development/OPFIN_B2B_INSTITUTIONAL_ACCEPTANCE_PROGRAMME.md)

## 1. How to read and maintain

The JSON is the **owned catalogue of 12 institutional types and 41 workstream-level capabilities**. This document explains the decisions; it is not a substitute for product acceptance.

- **Source foundation** means code/contracts have been identified. It does **not** mean end-to-end user acceptance, signed financial review, correct live deployment or a regulated service.
- **Candidate source** means incomplete/protected/default-off or awaiting exact-head completion.
- **Acceptance gap** means relevant partial code exists but a specific critical workflow, financial control or test remains open.
- **Target scope** means desired independently useful functionality not being claimed as already implemented.
- **Not attested** is not the same as **inactive**; inspect actual environment, signed authority and product activation per institution. The register intentionally carries no fabricated active-customer counts, verified licences, revenue, prices or completed tests.
- Each module starts with **owner TBD** and **acceptance pending independent evidence**. Assign an accountable product, engineering, finance, risk/legal and UAT contact, actual evidence and prioritised issue before promoting status.
- A supported generic Financial Space or a 15-module discovery menu does not prove an institution can perform an authorised financial workflow.

### Institutional readiness progression

**Discovered → Contacted → Contract proposed → Institution KYB reviewed → Workspace configured → Historical records approved → Role UAT → Product-specific financial/legal activation (if relevant) → Member/customer UAT → Commercial sign-off → Operationally accepted.**

These milestones are separate. For example, an Investment Club may become accepted for internal record keeping before any external investment custody or distributions are executed through OpFin.

## 2. Prioritised institutional register

| ID | Institution | Existing source basis (not live proof) | What must become institution-complete | Primary acceptance/risk gap | Commercial hypothesis | Operating KPI |
| --- | --- | --- | --- | --- | --- | --- |
| IC | **Investment Club** • P0 | Club capital, portfolio/valuation, treasury/import/reconciliation, distribution and statement source; dated backend tests | Member capital-to-investment-to-distribution with approvals, periodic financial close and member access | Current exact-head full client + accounting acceptance; verified external bank/custody and payout evidence | Club subscription, optional advanced reporting | Reconciled period close; accurate member capital/ownership |
| SC | **SACCO / Cooperative** • P0 | SACCO product/member/guarantee routes; candidate legal attestation and action controls | Shares, member savings, lending, guarantee exposure, subledger, reporting, branch/role controls | Verified legal authority/custody, double-entry and settlement, member journey | Scale-sensitive institution subscription | Reconciled member accounts; PAR/NPL and liquidity |
| SG | **Savings Group / VSLA** • P0 | Group cycle, pledges, matched contributions, welfare/loan proposals, share-out source | Whole cycle including repayment evidence, welfare and exact share-out reconciliation on mobile | No offline finality; secure drafts, role checks, external receipt and actual payout acceptance | Affordable group/subsidised fee | Verified contribution coverage, successful share-out |
| FI | **Bank / MFI / Lender** • P0 | Lender, product, channel, offers, finance and mandate contracts | Origination through funding, repayments, collection, recovery and provider reporting in dedicated tenant | Signed lender/product/funding authority, partner UAT, financially correct finality | Platform/servicing and lawful usage charges | Risk-adjusted margin, PAR/NPL, reconciled repayments |
| EM | **Employer / People** • P1 | Protected ten-module People source, mobile entry and scoped employment links | Whole hire-to-payroll-to-exit operational experience, independent backend, optional financial services | Statutory payroll and workforce/device UAT; employer privacy | Employer plus enabled-user subscription | Payroll accuracy, attendance/leave exceptions |
| FM | **Fund / Investment Manager** • P1 | Capital mandates, investor/financial reporting foundations | Mandates, investor entitlements, allocation, valuation, distributions and audited reports | Suitability, external custody, legal interest and mandate-level acceptance | Mandate/platform administration | Accurate custodian reconciliation and reports |
| IN | **Insurer / Broker** • P1 | Protection/policy/claim and partner reporting foundations | Quote-to-cover-to-premium-to-claim servicing for real approved provider | Insurer underwriting/issuance, actual premium and claim finality | Lawful platform, servicing/distribution fees | Policy-state accuracy, claims and premium reconciliation |
| PP | **Payment Provider / Wallet** • P1 | Payment adapter, durable intent, service economics and reconciliation | Provider-authorised transaction, recovery, reversal, exception and fee reporting | Real signed operator contract and external settlement UAT | Integration/transaction service charges where allowed | Payment exception age, duplication and cost |
| DP | **NGO / Programme** • P1 | Consented programmes, instrument, enrolment, impact reporting | Contract through field intervention, participant outcome and privacy-safe report | Actual metric definitions, participant controls, low-data field acceptance | Managed programme/reporting contract | Valid participant data and attributable outcome quality |
| MP | **Merchant / Supplier** • P1 | Customer-authorised finance intents, permissioned evidence candidate | Verified referral/consent through suitable financial product and partner cost reconciliation | Own-data consent, customer authority, real financier/beneficiary | Approved referral/integration arrangements | Confirmed intent-to-eligible service, cost to serve |
| TP | **Fintech / API / White label** • P2 | Developer centre, approved route docs and branded-Space requests | Scoped API onboarding, sandbox certification, domain proof, SLA, billing | Actual API/host approval, security/tenant isolation, finance-licence boundary | API/platform and support subscription | Time to certified integration, cost per active tenant |
| WA | **Welfare Association** • P2 | Reusable Financial Space governance/record foundation | Dues, fund rules, benefit claims, approvals, statements | Dedicated welfare ledger/role model, legal beneficiary and custody | Affordable member organisation subscription | Dues/claim reconciliation and dispute resolution |

**Delivery classes:** P0 is first institutional-completeness focus, **not** a ban on already authorised growth or a sequential gate to P1. All independently authorised live product operations continue under their own controls while gaps are closed.

## 3. Common capability blocks and specialised ownership

| Shared platform block | Common tool responsibility | Organisation-specific rule stays with |
| --- | --- | --- |
| Person + Financial Spaces | Identity, contexts, memberships, consent, invitations and scoped access | Institution role, member authority, private member information |
| Accounting infrastructure | Exact currency, immutable journals, dated transaction evidence, matching and statement freezing | Club ownership/NAV, SACCO savings/share liability, lender asset, welfare fund and member-loan policy |
| Workflows | Request, versioned policy, approval, delegation, audit, dispute | Board/committee mandates, maker/checker threshold, legal act |
| External events | Partner callbacks, idempotency, source hash, retry/recovery, reconciliation | Real provider of record and institution contractual responsibility |
| Product catalogue | Discover approved products and entitlement/state | Licences, actual funding/custody/insurer, age/country/channel, customer eligibility |
| Customer/member App | One consumer identity, Personal first, authorised Space switching | Actual member visibility, institution branding, specific permitted services |
| Reporting | Consistent evidence, period, currency, audit and export conventions | SACCO returns, club member capital, lender risk/ECL, insurer claims, programme impact |
| Commercial services | Agreement, approved fees/tax events, cost attribution, subscription and support | Pricing schedule, invoice/settlement and institution revenue ownership |

**No financial-operation shortcut** is permitted because two institutions share a component.

## 4. Four separately recorded readiness dimensions

A simple percentage complete would conceal operational risk. Record these four dimensions separately for each institution **and product**:

1. **Technical:** source/route exists; API and clients integrated; exact-head CI; cross-Space denial, connectivity, data migration and performance acceptance.
2. **Financial integrity:** contract/accounting owner, authoritative opening record, double-entry where applicable, maker/checker, external receipt finality, reversal, exception and close controls.
3. **Legal/service activation:** actual evidence for institution, service, territory, custody/funder/provider and customer/age permissions; obligations allocated to contracting parties.
4. **Commercial readiness:** institution willing to use/pay; onboarding cost known; correct fee/revenue events; support SLA and contribution margin forecast backed by assumptions.

A programme manager must not translate a 'source foundation' into 100% technical completion, and must not average a passing technical result with a missing licence.

## 5. Outcome and economics measures

The primary B2B KPI should be **Institutional Financially Completed Cycles**, segmented by institution type: an institution finishes its true operating cycle with required records, approvals, reconciliations and member/customer access intact.

Support it with:
- **Institution adoption:** truly active institutions, onboarding-to-first-value, member utilisation and subscription renewal.
- **Operational outcomes:** time to close books, contribution/repayment reconciliation, case resolution, absence of unaccounted money, payroll or claim completion.
- **Customer protection:** consent complaints, cross-Space denial, data corrections, support resolution and revoked access.
- **Economics:** recurring platform revenue, validated transaction/service fees, onboarding and ongoing cost, provider and communications costs, support load, contribution margin. No unproven financial partner income.
- **Provider truth:** verified externally settled flows and exceptions separately from configured or merely accepted internal intents.

## 6. Evidence and review governance

For every accepted capability, the register owner adds an acceptance link with **commit SHA, environment, date, actor role, exact task, server/financial source, expected vs observed results, failure/recovery tests and independent sign-off**. External institution agreement/licence and financial statements must remain in controlled evidence stores, not public repository files.

Review cadence: product owner at each change of source scope; finance/risk/legal before money-service activation; development/test leads at each candidate release; commercial operations before charging or making an availability claim. Keep statuses evidence-based, not time-based.

Named source families: [Current State](../CURRENT_STATE.md), [Source-to-Release Alignment](../operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md), [Club Accounting](../../apps/api/docs/api/CLUB_ACCOUNTING.md), [SACCO completion](../development/SACCO_AND_WHITE_LABEL_COMPLETION_2026-09-29.md), [People](../architecture/OPFIN_PEOPLE_CORE_RELEASE_SCOPE.md), [Lender Orchestration](../architecture/LENDER_ORCHESTRATION.md), [Inclusive Finance](INCLUSIVE_FINANCE_PROGRAMME_FRAMEWORK.md) and [Partner Reporting](PARTNER_FINANCIAL_COMPLIANCE_REPORTING_STANDARD.md).
