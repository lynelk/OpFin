# Financial Spaces commercial operations completion programme

Status: Implementation branch; not production activation
Branch: `feature/financial-spaces-commercial-operations-20260929`

## Target

Investment Club and SACCO Financial Spaces are financial operating environments, not dashboards. The target covers member ownership/capital, contributions, collections, withdrawals/disbursements, obligations, investments, valuation/NAV, distributions, fees, budgeting/cash flow, reconciliation, approvals, mandates, statements and member/portfolio analytics.

The existing club accounting subsystem remains the authoritative investment-club ownership/NAV ledger. This programme does not duplicate it.

## Commercial invariant

Every chargeable action requires an effective `commercial_pricing_rules` record. An intentional zero fee is valid; missing pricing is not. Rules can scope by Space type, partner, partner product and commercial agreement, and support flat, percentage and flat-plus-percentage charging.

No product or operational action should infer a fee from UI text or silently default an unknown commercial amount to zero.

## Money actions

`financial_space_action_intents` separates:
1. member/officer intent;
2. frozen commercial quote;
3. maker-checker approval;
4. provider submission;
5. provider finality;
6. accounting/revenue recognition;
7. reconciliation.

CPay remains the preferred configured money rail through the existing MobileMoneyService. An ambiguous provider result must be recovered by canonical reference, not retried as a new payment.

SACCO live money actions retain the Community Finance activation gate. Building routes and schemas does not activate deposit-taking, lending or distributions.

## Cito

Current source has certified contracts for Cito identity/credit capabilities and Essentials lending paths. No Cito messaging endpoint is invented here. Add messaging only when the Cito SMS/WhatsApp API path and payload/signature contract are supplied/certified. Until then, existing configured messaging adapters remain separate.

## Stolets

Stolets remains independent. OpFin consumes only governed, consented Financial Passport/alternative-data signals through the existing programme provider adapter. POS/inventory operations are not copied into OpFin.

## AudMon

No AudMon code contract exists in current OpFin source. Integration remains blocked pending an actual API contract, authentication model, event catalogue, ownership boundary and test environment. No guessed endpoint is acceptable.

## Remaining completion slices

- connect provider-finality callbacks to action-intent state and accounting posting;
- bind club accounting instructions to action intents where external money movement is required;
- complete SACCO member/share/savings/loan/guarantor/dividend subledgers and user journeys behind activation gates;
- complete Web and Flutter screens for operations, approvals, analytics and recovery;
- add Cito messaging after certified API contract;
- add AudMon after supplied contract;
- extend pricing administration, approval/versioning and partner settlement;
- end-to-end tests on PostgreSQL plus browser/device/accessibility and provider sandbox acceptance.
