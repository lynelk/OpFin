# OpFin web/client engineering rules

## Authority boundary

The web and mobile clients present/collect customer actions; `apps/api` owns authoritative identity, consent, scoring, affordability, pricing, loan state, provider finality, ledger, reconciliation and regulatory evidence.

Never duplicate financial formulas or manufacture provider success in a client.

## Current borrower UX

- Launch navigation is **Home | Borrow | Activity | More**.
- New-customer authentication is phone → OTP → names → six-digit PIN.
- A second phone is optional.
- KYC is NIN + ID front/back + photo holding ID.
- Available limit and amount due come from the credit-profile API.
- Formal offer terms come from the immutable API disclosure snapshot.
- Credit-information reporting consent is separate and explicit.
- Completed transactions expose e-receipts.
- Accessibility/PWD support is part of the normal journey.

## Money and finality

- Treat API money fields as integer minor units.
- New financial instructions require stable idempotency semantics.
- Pending/successful/failed/reversed/reconciled are distinct.
- Do not call a payment or payout complete until backend/provider finality says so.
- Keep step-up verification/session material inside established secure server/client boundaries.

## Production isolation

Production requires:

```text
NEXT_PUBLIC_USE_MOCK_API=false
OPFIN_ENABLE_DEMO_SHORTCUTS=false
```

Do not replace missing KYC, scoring, payment, savings or other provider state with fixtures in production.

## Accessibility

Preserve semantic labels, logical focus order, text scaling, reduced motion, icon+text actions and understandable recovery messages. A helper/accessibility flow must never require a customer to reveal PIN/OTP.

## API use

Before integrating a new route:

1. find it with `python3 scripts/search-api.py "<term>"`;
2. read `apps/api/docs/api/current-endpoints.md`;
3. use typed API helpers;
4. update client/web docs when the public contract or workflow changes.

## Quality gates

Web:

```bash
npm ci --legacy-peer-deps
npm run typecheck
npm run lint
npm run test
npm run build
```

Flutter:

```bash
cd ../client
flutter pub get
flutter analyze
flutter test
```

## Documentation

Current documentation is indexed from `docs/README.md`. Historical audit/demo material does not override current code/contracts.

A frontend workflow change must update `apps/web/README.md`, `apps/client/README.md`, `docs/LAUNCH_CUSTOMER_JOURNEY.md`, training docs or the relevant API contract as appropriate. CI checks for obvious documentation drift.
