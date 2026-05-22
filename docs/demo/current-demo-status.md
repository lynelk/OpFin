# Current Demo Status

Date: 2026-05-22

## Status

The frontend investor-demo path is connected to the backend API client and can run in either fixture mode or real backend mode.

Real backend mode requires:

```env
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=false
```

Fixture mode is still available for isolated UI review when `NEXT_PUBLIC_USE_MOCK_API` is omitted or set to anything other than `false`.

## Screens in the demo path

- Login.
- Customer dashboard.
- KYC status.
- Consent management.
- Loan application.
- Loan decision result.
- Loan offer.
- Loan account.
- Repayment schedule.
- Admin dashboard placeholder.
- Admin credit review.
- Admin audit trail.

## Current demo caveats

- Backend runtime was not available in this checkpoint, so browser rehearsal is still pending.
- npm was unavailable in this shell, so build, lint, typecheck, and tests could not run.
- KYC, decisioning, and mobile money remain clearly labelled demo/mock integrations.
- Savings, insurance, investments, and employer screens remain placeholders.

## Demo readiness checklist

- [ ] Install frontend dependencies.
- [ ] Run frontend checks.
- [ ] Start backend API with seeded demo data.
- [ ] Run frontend with real backend API mode.
- [ ] Verify customer demo flow in browser.
- [ ] Verify admin demo flow in browser.
- [ ] Capture final screenshot checklist.
