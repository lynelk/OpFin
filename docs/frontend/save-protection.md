# OpFin Save & Protection Frontend

## Status

Save and Protection are pilot customer and operations journeys backed by the production-shaped OpFin-BE contracts introduced in OpFin-BE PR #7 and the operations discovery queue added in PR #8. The frontend does not create its own wallet, savings balance, insurance underwriting result, policy issuance fact or claim decision.

## Responsibility Boundary

- OpFin owns customer experience, goals, product records, disclosures, servicing state, support context and product-level audit references.
- CPay executes payment collections and payouts through the backend mobile-money/payment orchestration boundary.
- The disclosed savings partner holds the savings position.
- The disclosed insurer or underwriter issues protection, owns underwriting/risk and controls claim decisions.
- A successful CPay collection is not automatically a confirmed savings position or an active insurance policy.
- Operations users record controlled external evidence. They do not manufacture partner truth inside OpFin.

## Customer Routes

### `/save`

Save & Protect journey summary. It shows confirmed savings, available savings and protection-policy state without combining unrelated money/risk states.

### `/savings`

Savings workspace:

- Lists only backend-approved savings products.
- Shows the partner-held custody notice.
- Lists existing goals and confirmed/available/reserved balances.
- Creates savings goals with optional targets and reminder schedules.
- Shows product minimums, maximums, lock/notice rules, disclosures and controlled terms.

### `/savings/[goalId]`

Savings goal servicing:

- Confirmed partner position.
- Available position after reserved withdrawals.
- Contribution reminders.
- Goal pause/resume.
- Explicit contribution initiation through CPay.
- Withdrawal request followed by partner release and CPay payout.
- Movement history with distinct product, payment-rail and partner-confirmation states.

Automatic debit remains disabled. Reminder schedules must not be represented as debit mandates until an approved recurring-payment mandate contract is implemented and certified.

### `/insurance`

Protection workspace:

- Lists only independently approved protection products.
- Shows insurer and underwriter identity.
- Shows premium, frequency, cover limit, benefits, exclusions, disclosure version and controlled terms.
- Requires explicit acceptance of the exact disclosure hash before enrollment.
- Lists existing policy records and whether insurer issuance has occurred.

### `/insurance/[policyId]`

Protection policy servicing:

- Policy and external insurer references.
- Insurer/underwriter and accepted disclosure evidence.
- Premium history, CPay state and insurer-settlement state.
- Cover period and next premium due date where issued.
- Claim submission for active policies.
- Claim status, decision reason and partner claim reference.
- Customer dispute submission for declined claims.

The UI must state that the insurer or underwriter is the claim decision authority. OpFin may track and facilitate the workflow but must not present itself as the claim approver.

## Operations Route

### `/admin/save-protection`

The operations console is restricted by the backend to platform administrators and operations users. It uses the backend work queue rather than asking staff to discover record IDs manually.

It provides:

- institution-scoped work queues for normal operations users and platform-wide queues for platform administrators;
- savings product draft creation and independent maker-checker activation;
- protection product draft creation and independent maker-checker activation;
- partner confirmation of collected savings contributions using partner reference plus SHA-256 evidence hash;
- partner release of savings withdrawals followed by CPay payout initiation;
- retry of partner-released withdrawals whose payout needs another controlled execution attempt;
- insurer premium-settlement confirmation using external evidence;
- insurer policy issuance recording with external policy number, partner reference and cover period;
- insurer/underwriter claim workflow transitions using partner references, decision notes and approved amounts where applicable.

The frontend never bypasses backend transition rules. If the API rejects a transition, maker-checker violation, stale state or incomplete evidence package, the UI presents the failure rather than inferring success.

## State Boundaries

### Savings contribution

`request -> CPay collection pending -> CPay success -> collected pending partner -> partner confirmed -> confirmed position`

Only the final partner-confirmed state contributes to the displayed confirmed savings position.

### Savings withdrawal

`request -> position reserved -> partner release -> CPay payout pending -> CPay success -> paid`

A requested withdrawal reduces available savings by reservation but does not reduce the confirmed position until payout completes.

### Protection activation

`disclosure acceptance -> enrollment/premium due -> CPay premium collection -> collected pending insurer -> insurer settlement -> pending issuance -> insurer issuance -> active cover`

A premium payment must never be presented as proof of active cover.

### Protection claim

`submitted -> insurer/underwriter review -> approved/declined -> paid/closed`

A declined claim may enter `disputed` and return to the partner workflow. OpFin does not substitute its own decision for the partner decision.

## Maker-Checker and Evidence

Customer-visible financial products are created as drafts. Activation requires an independent operations user, an approval reference, a controlled-evidence SHA-256 hash and a review note. Backend controls remain authoritative even when the UI offers the action.

Partner settlement/release evidence also requires a partner reference and SHA-256 evidence hash. These values should point back to controlled evidence held under the platform's document, audit and retention controls.

## Idempotency

Every customer-initiated savings contribution, savings withdrawal and premium payment is sent with a unique idempotency key. The backend remains authoritative for replay protection. Form re-submission must not be treated as authority to create another money movement.

## Error Handling

All Save & Protection requests use the authenticated server-side access token and the same `OpfinApiError` classifications as the rest of the web frontend. The UI exposes validation, authorization, network and server failures without inventing a successful product state.

## Sandbox Policy

`NEXT_PUBLIC_USE_MOCK_API=true` remains explicitly non-production. The sandbox mocks preserve the same key boundaries:

- savings custody is partner-held;
- contribution starts as collection pending, not confirmed;
- enrollment starts as premium due, not active cover;
- product creation starts as draft rather than active;
- insurer/underwriter remains the protection risk and claim authority;
- the operations work queue is structured but empty unless mock scenarios are intentionally added.

Production configuration continues to reject mock API mode and demo shortcuts.

## Release Gate

The Save & Protection web slice is complete only when:

1. TypeScript typecheck passes.
2. ESLint passes.
3. Vitest passes, including customer and operations Save & Protection contract tests.
4. Next.js production build passes with mock mode disabled.
5. Production mock/demo guards pass.
6. Flutter analysis/tests and Android release build remain green, proving the web changes did not destabilize the existing mobile application.
7. The deployed frontend points to an OpFin-BE release containing both the Save & Protection domain contract and the operations work-queue contract.
