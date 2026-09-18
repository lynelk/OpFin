# OpFin API Documentation

**Purpose:** current developer and operations documentation for the OpFin API  
**Last regenerated:** 18 September 2026  
**Scope:** `apps/api` plus the customer-facing contracts consumed by Flutter, web, WhatsApp and USSD

## Start here

### Developers

Read in this order:

1. `../../README.md` – repository and verification commands
2. `../../../docs/LAUNCH_CUSTOMER_JOURNEY.md` – launch product contract
3. `architecture/system-overview.md`
4. `architecture/api-design.md`
5. `architecture/security-and-compliance.md`
6. `architecture/testing-strategy.md`
7. `api/current-endpoints.md`
8. `api/frontend-backend-contract.md`

### Testers and operations

Use:

- `uat/customer-uat-scenarios.md`
- `operations/production-readiness-checklist.md`
- `../../../SECURITY.md`

## Current launch contract

The customer account and lending path is:

`Phone → OTP → names → 6-digit PIN → identity → credit profile → limit → request → offer → verified-wallet disbursement → repayment`.

The same API profile state drives mobile, WhatsApp and USSD. A second phone is optional. KYC requires NIN, ID front/back and a photo holding the ID. External source absence is recorded honestly rather than replaced with synthetic data.

Accessibility is part of the contract: simple language, screen-reader semantics, text scaling, reduced motion and assisted verification must remain compatible with the same security/KYC controls.

## Documentation rule

Documentation changes with the code. If an endpoint, financial state, authentication step, scoring source, customer label or release control changes, update the relevant document in the same pull request. Stale financial documentation is a defect.
