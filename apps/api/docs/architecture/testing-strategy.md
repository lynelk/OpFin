# Testing Strategy

## Testing Goals

Testing must protect money, identity, authorization, consent, and provider workflows. The backend should not rely on manual testing for financial correctness.

## Test Layers

### Unit Tests

Use for:

- Money calculations.
- Interest and fee calculations.
- State transition rules.
- Provider response parsers.
- Value objects.
- Permission helper logic.

### Feature Tests

Use for:

- API endpoints.
- Authentication and token lifecycle.
- Authorization policies.
- Validation failures.
- Loan application flows.
- Repayment and disbursement initiation.
- Admin web actions.

### Integration Tests

Use sandbox/fake adapters for:

- Airtel.
- MTN.
- CRB/KYC.
- SMS.
- Future insurance, investment, employer providers.

Do not hit live providers in CI.

### Migration Tests

Use for:

- Fresh database migration.
- Important rollback paths where safe.
- Constraints and indexes.
- Backfill jobs.

## Required Test Cases

Authentication:

- Login success/failure.
- Token issuance.
- Logout revocation.
- Password reset revokes old tokens.
- OTP expiry and attempt limits.

Authorization:

- Member cannot access another member's data.
- Institution admin cannot access another institution.
- OpFin admin can perform allowed operations.
- Unauthorized users cannot mutate financial state.

Financial:

- Loan application state transitions.
- Disbursement creates expected internal transaction.
- Repayment creates expected internal transaction.
- Duplicate callback does not double-post.
- Ledger entries balance.
- Reversals preserve history.

Provider:

- Invalid callback signature rejected.
- Stale callback rejected.
- Unknown provider reference handled safely.
- Provider timeout leaves pending/reviewable state.

Compliance:

- Consent is required for KYC/CRB.
- Audit event is emitted for financial/admin/KYC changes.
- Sensitive fields are redacted.

## Commands

Run when available:

```bash
composer install
php artisan test
./vendor/bin/pint --test
npm ci
npm run build
composer audit
npm audit
```

If local tooling is missing, record the exact command and error.

## CI Requirements

CI should run:

- Composer install.
- PHPUnit tests.
- Laravel Pint.
- Composer audit.
- NPM install.
- Vite build.
- NPM audit where practical.
- Secret scanning.

## Test Data Rules

- Use factories, not production-like personal data.
- Use fake phone numbers and fake NINs.
- Never commit real provider payloads unless fully redacted.
- Fixtures must be clearly named as test data.

