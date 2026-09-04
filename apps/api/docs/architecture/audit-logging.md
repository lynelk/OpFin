# Audit Logging

## Purpose

Audit logging provides evidence for financial integrity, regulatory compliance, user support, dispute resolution, incident response, and internal accountability.

## Events to Audit

Identity and access:

- Login success/failure where safe.
- Logout.
- Token revocation.
- Password reset.
- OTP verification.
- Role changes.
- Admin user creation/update/deactivation.

KYC and consent:

- Consent capture.
- Consent withdrawal.
- NIN validation request.
- CRB score request.
- KYC status changes.

Financial:

- Loan application created/submitted.
- Loan application approved/rejected/cancelled/disbursed.
- Loan created.
- Repayment initiated.
- Payment callback received.
- Transaction status changed.
- Ledger entry posted.
- Ledger entry reversed.
- Float topup created/approved.

Operations:

- Product/term changes.
- Institution changes.
- Compliance report generation.
- Data export.
- Account deletion/anonymization.

## Event Shape

Recommended `audit_events` fields:

- `id`
- `event_type`
- `actor_type`
- `actor_id`
- `subject_type`
- `subject_id`
- `institution_id`
- `correlation_id`
- `ip_address`
- `user_agent`
- `reason`
- `metadata_json`
- `created_at`

Recommended `audit_event_changes` fields:

- `audit_event_id`
- `field`
- `old_value_redacted`
- `new_value_redacted`
- `old_value_hash`
- `new_value_hash`

## Redaction Rules

Never store raw values in audit logs for:

- Passwords.
- OTPs.
- Access tokens.
- API keys.
- Private keys.
- Full NIN.
- Raw provider secrets.

Prefer masked values and hashes for sensitive identifiers.

## Correlation IDs

Every request should receive a correlation ID. The ID should be attached to:

- Application logs.
- Audit events.
- Provider requests.
- Provider callback events.
- Queue jobs.

## Append-Only Behavior

Audit events must not be updated to rewrite history. If correction is needed, append a correction event that references the original event.

## Developer Requirements

- Every new financial state change must include an audit event.
- Every new admin mutation must include an audit event.
- Every new KYC/consent action must include an audit event.
- Tests must assert audit events for sensitive workflows.

