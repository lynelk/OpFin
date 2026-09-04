# Data Privacy Review

Date: 2026-05-22

## Summary

OpFin has useful foundations for KYC, consent, audit logs, and support operations, but it still needs provider-backed KYC governance, retention rules, and audited export controls before replacing the live system.

## Current Controls

- Production KYC cases record status, provider, provider reference, evidence, review notes, risk flags, reviewer, timestamps, and expiry.
- Production consent records include purpose, policy version, status, channel, grant/revoke timestamps, and metadata.
- Consent revocation updates status and records audit evidence.
- Credit decision service checks active credit-processing consent before production decisioning.
- Admin/support operations create audit logs for sensitive actions.
- Frontend customer KYC screen now masks National ID display.
- Profile endpoint returns a minimized profile payload and does not expose National ID by default.

## Fixed in this pass

- Masked National ID/NIN display on the frontend KYC status screen.
- Reduced authentication response user payload to core session fields.
- Normalized OTP error responses so OTP existence/status is not unnecessarily exposed.
- Removed mock webhook secret value from `.env.example`.

## Critical / High-Risk Gaps

| Area | Current risk | Required action |
| --- | --- | --- |
| KYC evidence | KYC evidence storage and display controls are not fully defined. | Define allowed evidence fields, encrypt sensitive evidence where needed, and restrict admin visibility. |
| Data retention | No enforceable retention/deletion jobs are implemented. | Add retention schedules for KYC, consent, audit, support, OTP, and provider payloads. |
| Audit export | Audit logs exist but export/search/retention controls are incomplete. | Add export workflow, approval controls, filtering, and immutable storage policy. |
| Consent governance | Versioned consent exists, but policy publication and evidence export are incomplete. | Store consent text/version artifacts and exportable evidence. |
| Admin activity tracking | Audit coverage exists for newer operations but is not universal across legacy controllers. | Add audit middleware/service coverage to every privileged action. |
| Sensitive provider payloads | Mobile money/KYC/CRB payload minimization is not fully enforced. | Redact secrets and unnecessary PII before storing payloads or logs. |

## Medium / Low-Risk Items

- Add privacy notices and customer-facing consent copy review.
- Add support-user masking for sensitive fields unless elevated permission is granted.
- Add data subject access/export workflow if required by policy.
- Add scheduled cleanup for expired OTPs.

## Cutover Requirement

Do not cut over until:

1. KYC and consent data mapping from the live system is signed off.
2. Sensitive field masking rules are approved.
3. Retention and deletion policy is implemented or operationally controlled.
4. Audit export and admin activity reporting are approved by compliance.
