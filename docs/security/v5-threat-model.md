# OpFin v5 Production Threat Model

**Date:** 2026-08-26  
**Scope:** OpFin customer/admin APIs, OpFin ↔ CPay, authentication, KYC/consent, credit, savings, protection, reconciliation and operational control plane.

## Security objectives

1. Prevent unauthorized access to customer and financial records.
2. Prevent unauthorized or duplicate movement of money.
3. Preserve integrity and traceability of credit, savings, protection and reconciliation records.
4. Prevent callback forgery, replay and terminal-state regression.
5. Prevent cross-customer/cross-tenant data access.
6. Preserve service availability and recoverability.
7. Keep secrets and regulated data out of logs, source control and frontend bundles.

## Trust boundaries

- Public client ↔ OpFin API.
- Admin/operations client ↔ privileged OpFin API.
- OpFin ↔ CPay v2.
- CPay callback ingress ↔ OpFin webhook controller.
- OpFin ↔ CRB/KYC/communications integrations.
- Application ↔ database/cache/queue/object storage.
- CI/CD ↔ production environment and deployment credentials.

## Principal threats and controls

| Threat | Risk | Required controls / current design |
|---|---|---|
| Account takeover | Unauthorized access/financial action | Strong password validation, OTP reset, token revocation, throttling, secure sessions, step-up controls for sensitive actions, Security Centre. |
| Broken object authorization | Customer sees another customer's records | Authenticated routes plus ownership checks and authorization tests; privileged routes protected by role middleware. |
| Privilege escalation | Unauthorized admin/financial mutation | Explicit platform_admin/operations/support role scopes, maker-checker for governed rules/products/workflows, audit logging. |
| Duplicate payment request | Double debit/payout | Mandatory idempotency key, unique transaction persistence, idempotent replay semantics, CPay idempotency header. |
| Direct provider bypass | OpFin becomes second payment gateway | CPay-only provider manager, retired MTN/Airtel/Citotech payment code, CI forbidden-file/reference check. |
| Forged callback | False payment fulfilment | CPay callback HMAC, merchant validation, canonical signed input, raw-body validation, throttled webhook route. |
| Callback replay | Duplicate/late fulfilment | Timestamp replay window, signed nonce, unique webhook event ID, idempotent consumers and duplicate audit events. |
| Payment state regression | Successful payment overwritten by failure | Terminal-state transition guard rejects inconsistent callbacks. |
| Financial ledger tampering | Incorrect balances/obligations | Domain ledger services, immutable posting patterns, CPay reference retention, reconciliation exception workflow, audit logs; no raw DB correction process. |
| Rule/product manipulation | Unauthorized pricing/decision changes | Versioned Product Factory/Rules/Workflow definitions and approval transitions. |
| Consent bypass | Processing without valid authority | Canonical consent records, authenticated APIs, consent checks in regulated journeys, audit evidence. |
| Secret leakage | Compromise of CPay/CRB/KYC credentials | Environment variables only, no production values in source, secret review before launch, frontend cannot receive backend secrets. |
| Sensitive logging | PII/credential exposure | Structured logging policy; do not log secrets, raw credentials or full sensitive documents; audit metadata should use references/minimized context. |
| Injection / malformed input | Data corruption or code execution | Laravel validation, ORM/query builder, constrained enum/status inputs, JSON parsing failure handling. |
| DoS / brute force | Availability degradation | Route throttling, provider timeout/retry policy, queueing, health checks, horizontal scaling design, alerting. |
| Dependency compromise | Supply-chain risk | Locked dependencies, Composer audit, npm audit in frontend CI, controlled CI actions. |
| Data loss / regional outage | Business interruption | Dedicated managed database, backups, restore tests, RTO/RPO, runbooks and separate production infrastructure. |

## Abuse cases that must remain tested

- Password reset without/with expired OTP.
- Customer requests another customer's loan/application/profile resource.
- Non-privileged user calls admin decision/reconciliation/product/rule/workflow endpoints.
- Missing or reused payment idempotency key.
- Direct `mtn` or `airtel` provider selection.
- Invalid CPay callback signature.
- Stale CPay callback timestamp.
- Duplicate CPay event delivery.
- Terminal callback regression.
- CPay callback for unknown reference.
- Money movement with zero/negative or malformed minor units.
- Production configuration using mock payment provider or demo routes.

## Residual risks and operational controls

- Compromise of a valid privileged administrator still requires operational monitoring, maker-checker on financially material changes, rapid token revocation and incident response.
- CPay availability is an external dependency; OpFin must degrade safely, queue/retry only safe operations and expose pending/exception states rather than fabricate success.
- KYC/CRB data quality is outside OpFin's full control; provenance and verification status must be retained.
- Regulatory approval and country-policy activation remain organizational launch gates rather than software-only controls.

## Security release evidence

A release candidate is acceptable for technical security review only when:

- authorization and negative tests pass;
- replay/idempotency tests pass;
- CPay-only boundary CI passes;
- dependency audits pass;
- production secrets are configured outside source control;
- production debug is disabled;
- secure cookies/session settings are active;
- rollback and incident runbooks are available;
- backup and restore evidence is current;
- privileged roles and maker-checker configuration are reviewed.
