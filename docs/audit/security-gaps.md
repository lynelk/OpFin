# Security Gaps

## Critical Gaps

### Authorization Is Not Centralized

The application uses route middleware and some inline role checks, but there is no consistent policy/gate layer for users, institutions, loans, transactions, loan applications, products, accounts, float topups, chats, or admin actions.

Risk:

- Direct object reference bugs can recur when a controller accepts `{user}`, `{id}`, `{loan_id}`, `{institution}`, or `{transaction}` parameters.
- Admin/member/institution role behavior is spread across controllers and `InstitutionScope`.

Recommendation:

- Add Laravel policies for every financial object.
- Define roles and permissions in one place.
- Add tests for cross-user, cross-institution, admin, and super-admin boundaries.

### Payment Callback Trust Boundary Needs Formalization

Payment callbacks are public entrypoints. The working tree includes a shared secret check, but provider-native verification and replay protection still need a full design.

Risk:

- Forged callbacks can alter transaction status.
- Replay callbacks can double-process a financial event.
- Provider-specific payload verification can drift between Airtel, MTN, and Citotech flows.

Recommendation:

- Verify provider signatures or provider API status before marking money movement successful.
- Store callback events immutably before processing.
- Add idempotency keys and row locks around transaction status transitions.
- Reject stale callbacks outside a configured time window.

### Financial State Transitions Need Transactional Guarantees

Loan application creation, transaction creation, disbursement initiation, transaction callback processing, ledger entries, and repayment settlement are financially sensitive.

Risk:

- Partial writes can leave loan, transaction, schedule, and ledger state inconsistent.
- External API failure paths can produce rejected applications or failed transactions without full audit context.

Recommendation:

- Use database transactions around internal state transitions.
- Use explicit state machines for loan applications, loans, and transactions.
- Ensure every state transition creates an audit record.

## High Gaps

### Rate Limiting Is Too Generic

Laravel API throttling exists through `app/Http/Kernel.php`, but sensitive endpoints need route-specific limits.

Targets:

- login
- register
- generate OTP
- verify OTP
- reset password
- NIN validation
- credit score fetch
- payment callback endpoints
- loan application submission
- repayment initiation

### OTP Controls Are Incomplete

OTP records exist and OTP expiry is checked. Missing controls include attempt counters, resend throttles, hashed OTP storage, purpose binding, and one-time consumption across register/reset flows.

### Secrets and Environment Hygiene Need Tightening

The repository references many sensitive integrations: CRB, SMS gateways, mobile money providers, OpenAI, Pinecone, mail, AWS, Redis, and payment callback secret.

Needed:

- Complete `.env.example` coverage for all required production variables.
- Secret rotation runbook.
- CI secret scanning.
- A production environment contract.

### Logging May Leak Sensitive Data

Several services log provider response bodies and exception messages. For fintech and identity workflows, response bodies may include phone numbers, references, tokens, names, or provider details.

Needed:

- Structured logging with redaction.
- Request IDs and transaction IDs.
- No raw provider response logging in production.

### Account Deletion Flow Is Incomplete

`AuthController::beforeUserDelete` is marked as "to be implemented" and is not clearly wired into deletion. For financial systems, deletion must balance privacy requirements with ledger retention obligations.

Needed:

- Data retention policy.
- Pseudonymization strategy.
- Legal hold/accounting record preservation.
- User-accessible deletion/export workflow.

## Medium Gaps

- CORS defaults to localhost unless configured; production allowed origins should be explicit and tested.
- Public health endpoint exists but no deeper readiness endpoint is documented.
- No documented backup/restore validation.
- No dependency/security audit workflow is currently committed in the backend audit scope.
- No SAST/secret-scanning baseline is documented.
- RAG/chat integration lacks data classification and prompt-injection controls.
- Console test commands for payment providers should be gated from production misuse.

## Compliance Gaps

OpFin handles financial and identity-adjacent data. Before production use, document controls for:

- KYC/NIN data retention.
- Credit scoring consent and adverse-action explanations.
- Transaction auditability.
- Dispute handling.
- Fraud monitoring.
- Privacy policy mapping to implementation.
- Access review and least privilege.
- Incident response and breach notification.

