# Security Hardening Report

Date: 2026-05-22

Scope: OpFin-BE and OpFin-FE production replacement hardening for the current live system.

## Summary

This pass fixed several high-risk production-readiness gaps, but the platform is not ready for live-system cutover until the remaining critical areas are implemented and verified with a working PHP/Composer/npm toolchain.

## Fixed in this pass

### Backend

- Added explicit route throttling:
  - `throttle:auth` for login, registration, OTP, and reset-password routes.
  - `throttle:api` for authenticated API routes.
  - `throttle:webhooks` for callback routes.
- Added rate limiter definitions in `AppServiceProvider`.
- Hardened password policy for registration and password reset:
  - minimum 12 characters
  - mixed case
  - numbers
  - symbols
- Added token expiry at token creation using Sanctum expiration configuration.
- Removed duplicated manual API route registration from `AppServiceProvider`; Laravel routing is handled through `bootstrap/app.php`.
- Normalized API exception responses in `bootstrap/app.php` for:
  - unauthenticated requests
  - validation failures
  - HTTP exceptions
  - unexpected server errors
- Stopped leaking registration exception messages to clients.
- Reduced authentication response user payload to core identity/session fields.
- Updated `.env.example` toward production-safe defaults:
  - `APP_DEBUG=false`
  - `LOG_LEVEL=info`
  - `SESSION_ENCRYPT=true`
  - `SESSION_SECURE_COOKIE=true`
  - no mock mobile money webhook secret value.

### Frontend

- Added safe internal redirect handling for login `next` values.
- Added safe internal redirect handling for sandbox shortcut `next` values.
- Added secure, HTTP-only, same-site, expiring session cookie options.
- Added backend logout call before clearing frontend cookies.
- Added KYC/National ID masking on the customer KYC status display.

## Remaining Critical / High-Risk Items

| Area | Risk | Required action |
| --- | --- | --- |
| Backend toolchain | PHP/Composer/Pint are unavailable in this environment, so tests/builds could not be run. | Restore toolchain and run full backend verification before any readiness claim. |
| Frontend toolchain | npm is unavailable, so build/test/typecheck/lint could not be run. | Restore npm and run frontend verification before release. |
| Live provider security | MTN/Airtel/KYC/CRB production behavior is not certified here. | Complete provider sandbox certification, callback validation, replay protection, and runbooks. |
| Legacy financial paths | Some legacy decimal account/journal paths remain. | Finish migration to immutable integer minor-unit ledger for all financial state changes. |
| Admin authorization | Role middleware exists, but route-level permission granularity is still broad. | Move sensitive operations to permission/policy checks and add tests. |
| Session validation | Frontend middleware checks token presence and role cookie, not bearer-token validity. | Rely on backend for data access and consider server-side session introspection for sensitive admin pages. |
| OTP security | OTP generation is throttled and does not expose OTP in the response, but OTP attempts are not counted per code. | Add attempt counters, lockouts, and OTP audit events. |

## Medium / Low-Risk Items

- Add MFA for privileged admin roles.
- Add admin IP/device risk monitoring if operationally required.
- Add field-level frontend validation for all sensitive forms.
- Add audit export filters and retention policy enforcement.
- Add browser security headers if deployment platform does not provide them.

## Production Security Gate

No cutover until:

1. Backend tests and migrations pass.
2. Frontend typecheck/lint/test/build pass.
3. Critical provider security flows are certified.
4. Role and permission coverage is verified for every admin route.
5. Production environment variables are reviewed and no mock/demo flags are enabled.
