# Web registration acceptance: organisation, consent and browser E2E

Status: **draft branch only**, not deployed. Do not mark release complete without deployment and captured E2E results.

## Current implementation
- New individual and organisation representatives share OTP-backed personal account registration. Organisation intent redirects to `/setup/organisation` after identity registration.
- Existing authenticated Financial Space APIs create owned organisation spaces and save/reload organisation onboarding cases. The wizard supplies profile/KYB/regulatory/products/integration/certification stages; submission requires review. Group-type spaces follow separate membership governance.
- The API compares supplied Terms and Privacy versions against configured approved policy versions and atomically creates distinct auditable acceptance/acknowledgement records along with the user. Existing mobile registration remains backward compatible.

## Legal publication gates
The following values must be configured to match actual approved, version-controlled legal documents:
- API: `OPFIN_LEGAL_TERMS_VERSION`, `OPFIN_LEGAL_PRIVACY_VERSION`
- Web server: the same `OPFIN_LEGAL_TERMS_VERSION`, `OPFIN_LEGAL_PRIVACY_VERSION`
- Web build: `NEXT_PUBLIC_OPFIN_TERMS_URL`, `NEXT_PUBLIC_OPFIN_PRIVACY_URL`

**Never set invented versions or dummy legal URLs.** The repository has an existing API `/privacy-policy` route, but no audited published Terms of Service URL has been established for this work. Legal sign-off and reachable verified URLs are required before live customer registration.

## Production browser test plan
Execute against the exact deployed release SHA in an approved isolated test tenant using non-real test identity and provider-authorised SMS test numbers.
1. Visit public Get started, confirm browser lands on `/signup` and legal links are reachable HTTPS.
2. Individual path: phone → OTP → profile/PIN → authenticated `/dashboard`; confirm actual API-backed profile and service capability gate.
3. Organisation path: phone → OTP → account → `/setup/organisation` → create business → save profile → sign out → sign in → resume same record and stage → submit to review; confirm no regulated capability is activated.
4. Auth barriers: unauthenticated `/dashboard` and `/setup/organisation` redirect to login; unrelated customer cannot see another organisation.
5. Negative cases: duplicate phone, bad/expired OTP, too many OTP attempts, wrong PIN, weak PIN, privacy-policy version mismatch, missing acceptance, replayed verification token, interrupted session and role escalation attempt.
6. Mobile account created beforehand can sign into Web; Web account can sign into mobile without new identity.
7. Check response headers, cookies, audit event records and consent version in database with privacy-safe evidence.
8. Record browser video/screenshots with sensitive fields masked, CI run, deployment SHA, target URL, timestamps, test-account disposal, evidence of absence of real money movement, and rollback.

## Required safe setup
Production currently has only the `production` Railway environment. There is no isolated staging environment. The browser connector in this ChatGPT session is not connected to an originating browser context, and direct runtime networking cannot resolve the public hostname. **No production browser E2E has been executed from this session.** Use Work's cloud browser or a connected authorised browser plus an isolated test tenant. Do not fabricate a successful end-to-end run.

## Known follow-ups
- Extend the institutional wizard's evidence upload and regulator-specific requirements; not currently represented by the basic staged form.
- Add browser automation and negative-path tests to the repo's CI once a test environment and non-production Cito OTP fixtures are available.
- Confirm which live marketing website served the original screenshot, update its deployment source, then observe the live Get started click.
