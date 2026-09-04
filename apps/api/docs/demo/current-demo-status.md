# Current Demo Status

Date: 2026-05-22

## Demo readiness

The OpFin investor-demo vertical slice is code-complete at the integration-contract level. It can show a customer moving from login through consent, credit application, mock decisioning, offer acceptance, loan account creation, repayment schedule generation, ledger entries, and sandbox mobile money disbursement. Admin users can view the resulting snapshot and audit trail.

This status is not yet runtime-verified in the current shell because PHP, Composer, and npm are unavailable.

## Demo accounts

Seed data from the backend investor-demo seeder is expected to provide:

| Role | Login | Password | Purpose |
| --- | --- | --- | --- |
| Customer | `256700000001` | `password` | Customer demo flow. |
| Platform admin | `256700000099` | `password` | Admin snapshot and audit trail. |

Run the database seeders before relying on these credentials.

## Demo configuration

Frontend environment for real backend demo mode:

```env
NEXT_PUBLIC_OPFIN_API_URL=http://localhost:8000/api
NEXT_PUBLIC_USE_MOCK_API=false
```

Use fixture mode only for isolated frontend review:

```env
NEXT_PUBLIC_USE_MOCK_API=true
```

## Current working path

1. Customer login.
2. Dashboard and KYC status.
3. Credit-processing consent grant/revoke.
4. Loan application submission.
5. Mock affordability decision with reason codes.
6. Offer display.
7. Offer acceptance.
8. Loan account, schedule, ledger, and sandbox disbursement creation.
9. Admin review of customer, application, decision, loan, schedule, ledger, mobile money, and audit trail.

## Current limitations

- KYC is read from seeded backend user fields and is not live provider verification.
- Credit decisioning is deterministic mock logic for investor demonstration only.
- Mobile money disbursement is sandbox/mock only.
- Manual review/referred decisions are not implemented.
- Failed payment scenarios are not exposed through the investor-demo UI.
- Production compliance reporting is not implemented.
- Tests and builds must be rerun after the local PHP/Composer/npm toolchains are available.

## Demo-blocking checklist

- [ ] PHP and Composer available.
- [ ] Backend dependencies installed.
- [ ] Backend migrations and seeders pass from a fresh database.
- [ ] Backend tests pass.
- [ ] Frontend npm available.
- [ ] Frontend dependencies installed.
- [ ] Frontend typecheck, lint, tests, and build pass.
- [ ] Frontend is configured with `NEXT_PUBLIC_USE_MOCK_API=false`.
- [ ] Browser rehearsal completes with customer and admin accounts.
