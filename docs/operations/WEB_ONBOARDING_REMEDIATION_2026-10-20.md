# Web customer onboarding: implementation and release verification

Status: **DRAFT / NOT DEPLOYED / NOT END-TO-END VERIFIED** (20 October 2026).

## Source audit
- `apps/api/routes/api.php`: `POST /generate-otp`, `POST /verify-otp`, `POST /register`, `POST /login` already exist and are throttled.
- `apps/api/app/Http/Controllers/Api/AuthController.php`: shared phone verification, customer PIN hashing, role issuance, customer profile bootstrap and token response. Cito OTP support is feature-gated. Registration requires a one-time verification token.
- `apps/client/lib/register_screen.dart`, `otp_screen.dart` and `complete_registration_screen.dart`: existing mobile onboarding should remain unchanged.
- `apps/web/src/app/(auth)/login/page.tsx` and `apps/web/src/app/actions.ts`: existing Web sign-in uses phone/PIN, API access token and HTTP-only session cookies, redirects according to roles.
- `apps/web/src/app/(portal)/dashboard`: existing customer dashboard.
- `sites/opfin-public`: marketing-only site whose pre-existing call to action did not register users. Publishing source has not been confirmed as the same host as the screenshot.

## Change scope in this branch
- `/signup` phone → OTP → profile/PIN registration using shared OpFin APIs, without a second identity database.
- Verification token kept in short-lived secure HTTP-only cookie, never in URL/localStorage.
- Login entry and both repository-owned public entrypoints point to registration.
- No unearned organisation-admin role assignment: organisations initially onboard as personal identities and later require controlled Financial Space membership/onboarding.

## Remaining release blockers
1. Execute `npm run typecheck && npm run lint && npm run test && npm run build` under `apps/web`, with `NEXT_PUBLIC_USE_MOCK_API=false` and a correctly scoped API URL.
2. Confirm Cito OTP generation/verification/registration flow in staging; verify whether the Cito verification record satisfies both the `otps` verification-token check and `cito_otp_challenges` check in registration. Fail closed if either is missing.
3. Add automated registration and end-to-end tests, including duplicate phone, wrong/expired OTP, retries and rate limiting, weak/mismatched PIN, tampered verification cookie, mobile account sign-in, interrupted registration, cross-role access and account creation after retry.
4. Confirm production terms and privacy link destinations and record explicit acceptance policy version and timestamp (the existing API only accepts a terms boolean).
5. Confirm organisation creation/join/invitation and post-registration routing with explicit permissions. Implement organisation-specific wizard and resumable setup rather than giving organisation privileges on the account-type selection alone.
6. Test role-gated services and KYC eligibility on staging; registration must not enable lending or other regulated services prematurely.
7. Validate CSRF protections, session/token lifetime parity and server-side error logging; check accessibility and responsive UX.
8. Identify the actual published website source/hosting and ensure custom-domain routing, /signup availability, rollback plan and observed HTTPS staging/production smoke tests before cutover.
9. Verify website copy and API/operations documentation and complete controlled customer-data-migration/no-regression review.

**Do not merge or deploy based on this document alone.** Collect CI logs, test reports, release SHA, deployment URL, smoke-test evidence and rollback confirmation.
