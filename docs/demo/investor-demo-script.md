# OpFin Investor Demo Script

## Demo Accounts

- Customer: `256700000001` / `password`
- Platform admin: `256700000099` / `password`

Seed with:

```bash
php artisan db:seed --class=InvestorDemoSeeder
```

## Flow

1. Start the Laravel API and Next.js frontend.
2. Customer registers or logs in.
3. Customer opens the dashboard and verifies profile, KYC, consent, and latest application panels.
4. Customer opens KYC status and confirms seeded `VALID` NIN status.
5. Customer grants credit-processing consent. This is a demo consent record, not a production consent framework.
6. Customer submits a loan application for the seeded salary advance product.
7. Backend runs mock affordability and decisioning:
   - KYC verified.
   - Consent granted.
   - Requested amount within demo limit.
   - Estimated repayment within mock debt-service limit.
8. Customer views the decision and reason codes.
9. Customer views the offer and accepts it.
10. Backend creates the loan account, repayment schedule, ledger entries, and sandbox mobile money disbursement record.
11. Admin logs in and opens the credit review and audit trail screens.
12. Admin reviews customer, application, decision, offer, loan, ledger, schedule, mobile money, and audit trail data through `/api/demo/admin/investor-snapshot`.

## Mock Integration Labels

The demo labels these as mock-only:

- Affordability checks.
- Decisioning.
- Credit-processing consent lifecycle.
- Mobile money disbursement through the sandbox adapter.

No live CRB, mobile money, KYC, insurance, investment, or savings integrations are called.
