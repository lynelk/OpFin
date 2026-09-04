# Domain Boundaries

## Boundary Goals

Domain boundaries keep financial behavior understandable, testable, and auditable. New work should avoid placing business decisions directly in controllers or provider adapters.

## Proposed Domains

### Identity and Access

Responsibilities:

- Member accounts.
- Admin and institution users.
- Sanctum token lifecycle.
- Roles and permissions.
- Session/token revocation.
- Password reset and OTP verification.

Rules:

- Do not trust client-supplied user IDs for ownership.
- Use policies for every protected object.
- Separate authentication from authorization.

### KYC, Consent, and Credit Data

Responsibilities:

- NIN validation.
- CRB scoring.
- User consent capture and versioning.
- KYC status history.
- Data retention and access logging.

Rules:

- Every KYC/CRB lookup must have purpose and consent evidence.
- Sensitive provider payloads must be redacted before logs.
- Store durable summaries needed for compliance, not unnecessary raw data.

### Credit Products

Responsibilities:

- Loan products.
- Product terms.
- Eligibility rules.
- Interest, fees, limits, and disclosure versions.

Rules:

- Product terms must be versioned.
- New money/rate calculations require tests.
- Existing loans must keep the exact terms accepted at origination.

### Loan Lifecycle

Responsibilities:

- Loan applications.
- Approval/rejection/cancellation.
- Disbursement initiation.
- Active loan records.
- Repayment schedules.
- Closure and arrears.

Rules:

- Use explicit state transitions.
- Reject invalid transitions in one domain service.
- Audit every status change.

### Payments and Mobile Money

Responsibilities:

- Airtel and MTN collections.
- Airtel and MTN disbursements.
- Provider callbacks.
- Provider status polling.
- Idempotency and reconciliation.

Rules:

- Provider callbacks must be authenticated and idempotent.
- Internal transaction status must not depend on unverified payloads.
- Store provider references separately from internal references.

### Ledger and Accounting

Responsibilities:

- Accounts.
- Journal entries.
- Balanced postings.
- Reversals.
- Balance snapshots.
- Financial reporting source data.

Rules:

- Ledger records should be append-only.
- Use integer minor units.
- Every posting must balance before commit.
- Never update historical ledger entries to correct a mistake; post a reversal/correction.

### Savings, Investments, Insurance, and Employer Benefits

Responsibilities:

- Future savings wallets/goals.
- Future investment holdings/orders.
- Future insurance policies/premiums/claims.
- Future employer-linked benefit enrollment and payroll deductions.

Rules:

- Build these as separate bounded contexts, not as fields on loan models.
- Each module must have consent, ledger, audit, and provider reconciliation hooks from day one.

### Compliance and Reporting

Responsibilities:

- Audit reports.
- Regulator-facing summaries.
- Data retention reporting.
- Access review.
- Suspicious activity and exception reports.

Rules:

- Reports must be reproducible.
- Report data must trace back to source records and audit logs.
- Compliance exports must be permissioned and audited.

## Anti-Patterns to Avoid

- Controllers that directly decide financial state transitions.
- Mobile money services that update loans without a domain service.
- Shared `status` strings without an explicit state machine.
- Raw provider payloads scattered across unrelated tables.
- Cross-domain writes without database transactions and audit records.

