# Security and Compliance

## Security Goals

- Protect user funds, identity data, credit data, provider credentials, and operational access.
- Make every financial action attributable and reviewable.
- Prevent unauthorized cross-user or cross-institution access.
- Fail safely when providers or background workers misbehave.

## Authentication and Authorization

- Use Sanctum for API tokens.
- Use web session auth for admin portal unless moved to an API-driven frontend.
- Use policies/gates for object-level authorization.
- Test denied access as heavily as successful access.
- Keep role definitions centralized.

Required roles:

- Member.
- Employer user.
- Institution admin.
- OpFin admin.
- Super admin.
- Compliance officer.
- Support/operations user with limited permissions.

## Data Protection

Sensitive data includes:

- Phone numbers.
- NIN.
- Date of birth.
- Credit score data.
- Loan details.
- Transaction references.
- Provider payloads.
- OTPs.
- Access tokens.
- Employer/payroll data.
- Insurance and investment records.

Rules:

- Redact logs.
- Encrypt secrets.
- Hash OTPs in new code.
- Do not expose provider payloads to clients.
- Limit admin access by role and purpose.

## Compliance Controls

Required evidence:

- User consent version and timestamp.
- KYC/CRB request purpose.
- Actor for every admin action.
- Before/after change records for sensitive fields.
- Financial state transition history.
- Provider callback/event history.
- Report generation history.

## Operational Security

- Production `APP_DEBUG` must be false.
- CORS must be explicit.
- Secrets must come from environment/secret manager.
- Provider sandbox and live credentials must be separated.
- Failed jobs must be monitored.
- Queue retries must be idempotent.
- Backups must be tested.

## Provider Security

For each provider:

- Separate sandbox and production config.
- Verify callback authenticity.
- Store callback events.
- Reconcile internal and external status.
- Do not trust client-submitted provider references.
- Rotate credentials on schedule and after incidents.

## Compliance Reporting

Compliance reports must:

- Be permissioned.
- Be audit logged.
- Be reproducible.
- Avoid exposing more personal data than required.
- Include report parameters, actor, timestamp, and source data cutoff.

## Incident Response

Security incidents must preserve evidence:

- Freeze related logs.
- Preserve provider callback payloads.
- Record timeline.
- Revoke affected tokens.
- Rotate affected secrets.
- Mark suspicious financial records for review.

