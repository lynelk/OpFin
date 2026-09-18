# Security hardening report

Updated: 18 September 2026

## Current position

The May 2026 scaffold-era security report is superseded. The current OpFin monorepo has repeatable CI/security/deployment gates and production-shaped customer, financial and regulatory controls. This document records the present hardening position; it is not a penetration-test certificate.

## Implemented controls

### Authentication and customer identity

- phone ownership by expiring, attempt-limited OTP;
- six-digit PIN for new mobile customers;
- predictable PIN rejection and login rate limiting;
- token/session revocation on reset/deletion paths;
- private KYC evidence;
- NIN + ID front/back + photo holding ID;
- provider-backed NIN/liveness/face/NIN-phone states;
- no broad SMS/contact/gallery permissions for loan decisioning.

### Authorisation and client boundaries

- backend remains authoritative for roles/financial state;
- protected API routes and role/permission checks;
- provider secrets remain backend-only;
- production web fails closed on mock/demo flags;
- WhatsApp webhook signatures and session controls;
- USSD callback authentication configuration;
- PIN/OTP never requested in WhatsApp/USSD support.

### Financial integrity

- CPay-only production money-movement boundary;
- integer minor units on new production paths;
- idempotent financial instructions;
- provider finality separated from request acknowledgement;
- immutable balanced ledger;
- append-only reversal/correction paths;
- reconciliation exceptions remain explicit;
- receipts/reporting side effects execute only after committed financial finality.

### Credit and regulatory controls

- attributable CRB/MNO/approved-partner/internal score components;
- missing source data remains unavailable rather than fabricated;
- verified affordability gate for automatic approval;
- immutable formal offer/disclosure hash;
- separate electronic consent for positive/negative credit-information reporting;
- reporting data-quality and consent holds;
- complaint regulatory due/SLA evidence;
- NPL/default-interest cap tracking/enforcement;
- maximum-two manually entered guarantor contacts;
- direct rate mutations blocked outside governed approval;
- prior UMRA approval evidence required for interest-rate changes;
- hashed/validated regulator evidence packs.

### Release and supply-chain controls

The exact candidate must pass:

- OpFin Monorepo CI;
- dependency audits;
- PHP formatting/tests;
- web typecheck/lint/tests/production build;
- Android/iOS analysis/tests/release compile;
- repository secret/artifact controls;
- security monitoring;
- deployment contract;
- documentation-drift check.

## External controls still requiring production evidence

- verified licensed entity/UMRA/lender status;
- identity/bureau/affordability/CPay provider certification;
- private KYC storage configuration and retention policy;
- official complaint contacts and staffing;
- backup/restore drill;
- monitoring/alerting;
- independent security assessment;
- real-device accessibility/PWD UAT;
- privileged-access review and incident rehearsal.

## Security rule

A green build proves the checked source candidate passed the configured gates. It does not prove external provider correctness, regulator approval or absence of every vulnerability. Production evidence must remain explicit.
