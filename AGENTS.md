# AGENTS.md

Guidance for agents and engineers working on the OpFin Laravel backend.

## Project Context

OpFin is a Uganda-first personal finance platform covering responsible credit, savings, investments, insurance, employer-linked benefits, KYC, user consent, audit logging, mobile money integrations, CRB integrations, and compliance reporting.

Treat this repository as financial infrastructure. Correctness, auditability, authorization, privacy, and operational safety matter more than speed.

## Core Rules

- Do not implement new business features without a clear requirement, tests, and migration plan.
- Do not make unaudited financial state changes.
- Do not bypass authorization for convenience.
- Do not hardcode secrets, API keys, tokens, phone numbers, private keys, credentials, provider URLs, or production identifiers.
- Do not fake live integrations. Use explicit sandbox/test adapters, fixtures, or mocks named as such.
- Do not log raw secrets, tokens, OTPs, NINs, access tokens, provider credentials, or full sensitive provider payloads.
- Do not store money as floats, doubles, or decimal strings in new code.

## Laravel and PHP Standards

- Target Laravel 11 and PHP 8.2+.
- Follow Laravel conventions for controllers, form requests, policies, jobs, services, events, listeners, models, factories, and migrations.
- Keep controllers thin. Put domain behavior in services/actions with tests.
- Use dependency injection instead of constructing service dependencies inline when practical.
- Prefer explicit return types for new PHP methods.
- Use Laravel collections, validation, authorization, queues, transactions, casts, and configuration APIs instead of ad hoc helpers.
- Run Laravel Pint before committing PHP formatting changes where available.

## Sanctum Authentication

- API authentication must use Laravel Sanctum.
- Protected mobile/API routes must use `auth:sanctum`.
- Token issuance must happen only after successful credential or verified OTP flows.
- Logout must revoke the current token.
- Password reset or account compromise flows must revoke existing tokens.
- Never expose Sanctum tokens in logs, query strings, redirects, or exception messages.

## Roles and Permissions

- Use centralized authorization with policies, gates, or a dedicated permissions layer.
- Do not rely on scattered inline role checks as the only protection.
- Every user, institution, loan, transaction, account, product, float, KYC, consent, and report action must have an explicit authorization decision.
- Test cross-user and cross-institution access denial.
- Keep member, employer, institution admin, OpFin admin, and super-admin responsibilities separate.

## Audit Logging

- Financial, KYC, consent, role, institution, and admin state changes must be audit logged.
- Audit logs must include actor, subject, action, timestamp, request or correlation ID, source IP when available, before/after state where safe, and reason/comment when applicable.
- Audit logs must be append-only. Prefer reversal/correction records over destructive edits.
- Do not log raw sensitive values when a masked or hashed value is enough.

## Money Handling

- New money fields and calculations must use integer minor units only, such as Ugandan shillings as integer UGX units where no fractional unit applies.
- Do not use float/double for balances, fees, interest, repayments, disbursements, limits, premiums, or investment amounts.
- Use named value objects or explicit integer fields for money where possible.
- Currency must be explicit on new financial tables and APIs.
- Financial calculations must be deterministic and covered by tests.
- Ledger entries must balance before commit.

## Database and Migrations

- Migrations must be forward-safe and production-conscious.
- Do not edit old migrations after they have been shared or deployed; add new migrations.
- Use foreign keys, indexes, uniqueness constraints, and check-like validation where the database supports it.
- Use nullable columns intentionally and document transitional nullable states.
- Financial records should use immutable or append-only patterns where possible.
- Add indexes for high-cardinality lookup paths, especially user, institution, transaction reference, status, created date, provider reference, and idempotency keys.
- Avoid destructive data migrations without backup, rollback, and audit plans.

## API Validation

- Validate all API input with form requests or explicit validators.
- Do not trust client-supplied user IDs, institution IDs, roles, statuses, amounts, rates, callback statuses, or provider references.
- Derive the authenticated user from Sanctum, not request payload.
- Validate amount ranges, currency, phone formats, enum statuses, dates, and provider payload structure.
- Return consistent JSON error shapes.
- Do not leak internal exception details to clients in production responses.

## Testing Expectations

- Add or update tests for every behavior change.
- Security-sensitive changes require regression tests for allowed and denied cases.
- Financial calculations require unit tests with edge cases.
- Payment callbacks require idempotency, replay, invalid signature, and status transition tests.
- Authorization changes require cross-user, cross-institution, member/admin, and unauthenticated tests.
- Migrations should be exercised through feature tests or migration checks where practical.

Minimum local/CI commands when available:

```bash
composer install
php artisan test
./vendor/bin/pint --test
npm ci
npm run build
```

Useful static/security checks when added to the project:

```bash
composer audit
npm audit
php artisan route:list
php artisan config:cache
php artisan optimize:clear
```

If a command is unavailable in the local environment, document the exact error and do not claim the check passed.

## Security Rules

- No hardcoded secrets.
- No unauthenticated financial mutations.
- No unaudited admin mutations.
- No provider callback processing without signature/shared-secret verification and idempotency.
- No OTP storage in plaintext in new code; hash OTPs and enforce expiry, purpose, resend limits, and attempt limits.
- No sensitive data in logs.
- No public debug mode in production.
- No broad CORS in production.
- No direct object access without ownership or permission checks.
- No raw SQL with user input unless parameterized.
- No file uploads without type, size, storage, and malware-scan controls.
- No queue job that mutates financial state without retry/idempotency strategy.

## Integration Rules

- Mobile money, CRB, KYC, insurance, employer, banking, and investment integrations must have explicit sandbox and production configuration.
- Live integrations must not be simulated by silently returning success.
- Provider response handling must persist enough evidence for reconciliation without leaking secrets.
- External state must be reconciled against internal state before final financial settlement.
- Provider outages must fail safely and leave transactions in a reviewable pending/failed state.

## Compliance Reporting

- Consent must be versioned and timestamped.
- KYC and CRB access must be justified, logged, and linked to user consent where required.
- Reports must be reproducible from stored data and audit logs.
- Deletion/anonymization must respect financial record retention requirements.
- Admin access to compliance reports must be permissioned and audited.

## Commit Discipline

- Keep commits focused.
- Do not mix audit docs, security fixes, formatting sweeps, and feature work in one commit.
- Never stage unrelated user or agent changes.
- Before committing, review `git status --short` and `git diff --cached --name-only`.
