# Production Readiness Checklist

Date: 2026-05-22

Use this checklist before approving OpFin as a replacement for the current live system.

## Backend Security

- [x] Sanctum token authentication exists.
- [x] Auth, API, and webhook routes have explicit throttling.
- [x] Registration and password reset enforce a stronger password policy.
- [x] Password reset requires OTP and revokes existing tokens.
- [x] API exceptions are normalized and do not expose stack traces.
- [x] Demo routes are disabled by default outside local/testing.
- [x] Production boot blocks `APP_DEBUG=true`, mock mobile money provider, and enabled demo routes.
- [ ] MFA or elevated verification exists for privileged admin roles.
- [ ] Fine-grained permission/policy checks cover every admin operation.
- [ ] Backend tests and migrations pass in CI/local toolchain.

## Frontend Security

- [x] Protected routes require an access token cookie.
- [x] Admin routes are role-aware.
- [x] Sandbox shortcuts are disabled in production builds.
- [x] Production builds fail when mock API mode is enabled.
- [x] Login redirects are constrained to internal paths.
- [x] Session cookies are HTTP-only, same-site, secure in production, and expiring.
- [x] Logout calls backend token revocation before clearing cookies.
- [ ] Frontend build, typecheck, lint, and tests pass in toolchain.
- [ ] Sensitive admin pages perform server-side token/role validation beyond cookie presence.

## Financial Controls

- [x] Production ledger service enforces balanced entries.
- [x] Production ledger entries use integer minor units.
- [x] Successful legacy disbursement/repayment flows post production ledger transactions.
- [x] Mobile money adapter layer has idempotency structures.
- [x] Financial postings added through loan service are audited.
- [ ] All legacy decimal/string money fields are retired or migrated.
- [ ] All balance changes are routed exclusively through production ledger services.
- [ ] Reversal and correction workflows are production complete.
- [ ] Provider reconciliation is complete and signed off.

## Data Privacy and Compliance

- [x] Consent records include purpose, policy version, status, and timestamps.
- [x] Consent revocation blocks future production credit processing through decision gates.
- [x] Customer KYC display masks National ID in the frontend.
- [x] Profile endpoint uses a minimized payload.
- [ ] KYC evidence retention/encryption/masking policy is implemented.
- [ ] Audit export/search/retention workflow is complete.
- [ ] Admin activity tracking covers every legacy and production privileged action.
- [ ] Compliance reports are regulator-ready and approved.

## Operational Readiness

- [x] `/api/health` exists.
- [x] Database queue configuration exists.
- [x] Scheduler configuration exists.
- [x] Support case, reconciliation, compliance, and ledger admin foundations exist.
- [x] Production operations, queue, monitoring, backup, incident, and callback runbooks are documented.
- [ ] Queue workers and failed-job handling are verified.
- [ ] Monitoring and alerting are active.
- [ ] Backup and restore drill is complete.
- [ ] Provider callback monitoring is active.
- [ ] Incident and rollback runbooks are rehearsed.

Runbook references:

- `docs/production/operations-runbook.md`
- `docs/production/queue-and-scheduler-runbook.md`
- `docs/production/monitoring-and-alerting-plan.md`
- `docs/production/backup-and-restore-plan.md`
- `docs/production/incident-response-runbook.md`
- `docs/production/provider-callback-runbook.md`

## Replacement Decision

Current status: not ready for production replacement.

The codebase has stronger guardrails after this hardening pass, but live-system cutover remains blocked until provider integrations, financial migration, admin operations, compliance reporting, and full toolchain verification are complete.
