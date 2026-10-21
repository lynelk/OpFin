# OpFin optional merchant finance: consent and lender authority close-out

**Status:** Code controls implemented as release candidates; real customer authority, merchant ownership and lender legal evidence are not yet provided or accepted. **Do not mark as approved.** Updated 20 October 2026.

**North Star:** Every financial life deserves a future worth looking forward to. This is an optional merchant finance feature, not a change to OpFin's personal-first universal platform.

## 1. Consent acceptance matrix

| Control | Implemented source/operation | Evidence required before production |
| --- | --- | --- |
| Borrower is identified | `BusinessPassportShadowService` queries stored borrower-owned consent and exact linked Financial Space | Reviewed KYC identity, lawful user authorisation and correct Financial Space membership; no stand-in identity |
| Customer authorises OpFin to assess business records | Latest active `ConsentRecord` with purpose `merchant_finance_assessment`, scope matches `financial_space_id`, `stolets_tenant_id`, and `lender_recipient_id` | Real named borrower acknowledgement after seeing source, lender legal name, scope, purposes, retention and how to revoke; current exact grant + policy version |
| Merchant authorises Stolets source sharing | Stolets source `DataAccessService.assertCreditEvidence` independently validates active stored share and grant with purpose `credit_assessment` | Consented merchant owner, exact lender-of-record recipient ID, field/period categories and granted/revoked audit history |
| Trusted data/owner linkage | OpFin checks tenant reference in Financial Space metadata; Stolets independently validates source quality/fact membership/freshness | Independently witnessed merchant-business mapping and effective merchant control; source tenant ID cannot alone prove beneficial ownership |
| Withdrawal and corrections | OpFin latest-grant revocation blocks new fetches; Stolets source grant revocation blocks new evidence | Live dual-system revocation replay, stale offline device, re-grant and access-record tests; retention/correction procedure and notification |
| Operator access | Admin-only API, audited success/failure, default-off partner configuration | Named data protection approver; least-privilege accounts and partner-secret ownership; accepted access/retention logging and deletion rules |

**Do not treat** synthetic user IDs, development grants, test database records, product parameters or mere user-click assertions as production acceptance.

## 2. Lender legal and funded-authority matrix

| Control | Technical check now | Real documentary evidence still required |
| --- | --- | --- |
| Actual lender of record | Active `LoanProduct` references active `Institution`; Stolets receives `opfin-lender-{institutionId}` as separately scoped recipient | Legal entity registration; exact legal name and outward-facing lender disclosure; mapping of opaque integration recipient to that entity |
| Legal lending authority | `LenderAuthorityEvidence::complete` checks configured basis, authority reference and regulator; expiry is checked | Independently verified current licence/exemption, country and product scope, regulator, restrictions, validity and original documentary reference |
| Lender mandate and partner contract | No shadow money or contract execution possible | Executed loan origination, servicing and collection terms; entity responsibility; borrower complaints and credit-information reporting; suitable data-sharing roles |
| Funding and investor money | Shadow endpoint has no fund reserve, disbursement, ledger, price or investment capability | Signed capital mandate, verified available cash, fund/beneficial ownership, lender/investor rights, settlement bank/wallet and safeguarding evidence |
| Financial policy | Existing canonical `CreditEconomicsService` and credit distributions remain unchanged | Approved interest/fee schedule and cost disclosures; Uganda regulator/product caps, tax/EFRIS determination, independently recomputed unit economics |
| Operational closure | Configuration-only readiness and manual shadow evidence | Real Cito routing and provider certifications, reconciliation and ledger invariants, adverse-event and stop-loss policies, maker-checker approval, incident procedure |

## 3. Evidence pack ownership

**Legal/compliance:** legal lender name; licence/exemption and regulator/product/country scope; authority verification date and verifier; legal contract and data-controller responsibilities; privacy notice and merchant-consent language.

**Risk/credit:** proposed product/term policy, affordability formula, negative/referral reasons, model lineage, adverse-action explanations, stress and portfolio limit/stop-loss thresholds.

**Finance/treasury:** independently verified funding commitments and settled cash; price-to-fund cost attribution; disbursement/repayment/reversal reconciliation and investor custody/waterfall where applicable.

**Product/operations:** applicant/business owner identity-link evidence, two-sided real grant/revocation logs, consent expiry, actual environment entitlement, source data quality, provider idempotency/finality and contingency/rollback evidence.

**Engineering/security:** PRs and exact SHAs, CI results, isolated tenant/consent and wrong-lender tests, credentials in secret storage, rotation, partner IP/network controls, production deployment and independent post-deploy UAT. **Stolets GitHub Actions is currently unable to run because of an Actions budget restriction.**

## 4. Release statuses and closure decision

- `software_candidate`: isolated tests pass, code on reviewed PR, no live permissions.
- `integration_tested`: both deployed **sandbox** counterparts successfully exchange real signed/scoped fixtures under independent secrets, negative tests and recorder traces, with no borrower offer or ledger movement.
- `legal_and_funding_verified`: named independent approvers accept licence, lender, capital, settlement, customer notice and actual consenting borrower/merchant records; country/product and environmental scope are documented.
- `pilot_approved`: dedicated lender/risk/legal/finance approval of a defined new experimental merchant product, stop-loss/runbook and monitoring; does not constrain existing authorised unrelated products.
- `live_accepted`: exact release SHAs, all mandatory CI green, real consent/KYB/financial readiness, observed provider finality/reconciliation and sign-off; **only then** consider independent live loan origination.

**Current status:** `software_candidate` for the optional **shadow-only** connector, pending full OpFin and Stolets release checks. No real consent artefact, independent lender licence verification, funding settlement evidence or live provider acceptance was supplied. Therefore **consent and lender authority are not resolved at the external acceptance level**. Do not close issue #176 or #190 solely because application code can enforce those boundaries.

Cross-repo source candidates: [OpFin #201](https://github.com/lynelk/OpFin/pull/201), [Stolets #226](https://github.com/lynelk/Stolets/pull/226).
