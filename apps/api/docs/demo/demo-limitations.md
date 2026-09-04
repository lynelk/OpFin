# Investor Demo Known Limitations

This vertical slice is intentionally demo-grade.

## Mocked

- Affordability uses deterministic mock rules.
- Decisioning uses reason-code rules in `InvestorDemoService`.
- Consent is scoped to `credit_processing` for the demo only.
- Mobile money disbursement uses the mock/sandbox adapter.
- Seeded KYC status is stored on the user record.

## Not Connected

- Live mobile money providers.
- CRB providers.
- Production KYC providers.
- Insurance, savings, or investment providers.
- Employer payroll or benefit systems.

## Financial Caveats

- Legacy loan, transaction, account, and journal tables still use older money column styles in places.
- The demo creates audit logs for consent, application, decision, offer, loan account, and disbursement events, but the wider legacy backend still needs full audit coverage.
- This is not a production credit policy, underwriting policy, or compliance decision engine.

## Production Readiness Work

- Replace mock affordability with a governed policy service.
- Replace demo consent with a full consent module and revocation gates.
- Complete integer minor-unit migration for all financial tables.
- Add formal loan-offer lifecycle controls.
- Add outbox/idempotency handling for all external side effects.
