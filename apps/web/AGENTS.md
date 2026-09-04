# AGENTS.md

Guidance for agents and engineers working on the OpFin web and mobile frontends.

## Authority boundary

The frontend presents and initiates OpFin product workflows. It does not own authoritative financial calculations or settlement truth.

Backend-owned values include pricing, fees, interest, repayment schedules, outstanding balances, savings/protection balances, funding capacity, asset-finance economics, ledger/accounting state, reconciliation state and provider finality.

Do not duplicate those formulas in React, server actions, Flutter or local storage merely to make a screen convenient. Consume the backend contract and display the result.

## Money and idempotency

- Treat monetary API fields as integer minor units.
- Never convert authoritative money to floating-point values for business logic.
- UI formatting may add grouping or currency labels but must not change the underlying integer value.
- Generate a fresh idempotency key for a new money instruction and preserve it for intentional retry/replay.
- Reusing a key with a changed amount, source, party or action is a client bug and the backend will reject it.
- Never mark an action settled because the request was submitted. Pending, successful, failed, reversed and reconciled are distinct states.

## Step-up financial actions

High-risk financial instructions must keep verification material server-side:

```text
create governed backend intent
→ request OTP for registered phone
→ user confirms OTP
→ server verifies OTP
→ short-lived verification token remains server-side
→ server confirms financial intent
→ backend executes through CPay
```

Do not expose verification tokens to client components, query strings, browser storage or analytics.

## Production and demo isolation

Production must use:

```text
NEXT_PUBLIC_USE_MOCK_API=false
OPFIN_ENABLE_DEMO_SHORTCUTS=false
```

Mocks and demo endpoints are permitted only when explicitly labelled and gated. Production UI must not fall back to fabricated customer balances, provider success, KYC/CRB status, product availability or settlement results when an integration is unavailable.

## Security

- Provider credentials and secrets never belong in frontend environment variables or source.
- Only genuinely browser-safe configuration may use `NEXT_PUBLIC_`.
- Keep auth/session material in the established secure server/cookie boundary.
- Do not log OTPs, access tokens, personal identity evidence or raw sensitive provider responses.
- Preserve backend authorization errors; do not bypass them with hidden UI assumptions.

## Offline-aware mobile behavior

Offline queues may persist permitted user events with stable batch references. Offline mode does not authorize offline money movement.

- Keep queued event IDs stable across retry.
- Surface backend conflict/review state rather than silently overwriting.
- Do not manufacture timestamps, settlement or external provider confirmation.
- A changed offline payload requires a different batch reference.

## Frontend engineering rules

- Keep server actions thin and route financial decisions to backend services.
- Prefer typed API contracts and shared formatting helpers over duplicated ad hoc parsing.
- Use accessible form labels, error messages, keyboard flows and loading/state feedback.
- Avoid stale hand-coded product totals when the backend already returns aggregate values.
- Remove dead imports, unreachable branches, obsolete mock fallbacks and stale comments when non-use is verified by tests/search.
- Do not delete compatibility/mobile code merely because it looks old; prove it is unused first.

## Quality gates

Web:

```bash
npm ci
npm run typecheck
npm run lint
npm run test
npm run build
npm run check
```

Flutter when mobile code changes:

```bash
cd ../client
flutter pub get
flutter analyze
flutter test
flutter build apk --release
```

A frontend financial release is complete only when the corresponding backend contract is deployed and healthy. A green UI build does not certify a money rail, a fact that computers apparently require us to state explicitly.

## Documentation

The root `README.md` and this file describe current frontend behavior. Backend financial and integration rules are authoritative in `lynelk/OpFin-BE` root documentation and its current production/integration docs.
