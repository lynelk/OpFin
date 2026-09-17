# OpFin security

Security is maintained through layered controls, verified changes and timely response. A successful scan is not a certification that a system is free of vulnerabilities. Do not describe this repository or a release as permanently secure.

## Reporting

Do not publish passwords, identity documents, account numbers, access tokens, production logs or exploitable customer records in a public issue. Report concerns privately to the repository owner or the authorised OpFin security contact. A dedicated security mailbox and private vulnerability-reporting configuration must be confirmed by the owner before they are advertised. Never invent a support or security address.

## Required release checks

The following workflows must pass on the exact commit to be released:

- `OpFin Monorepo CI`: layout, current-index secret scan, API tests/audit, API asset audit/build, web audit/typecheck/lint/tests/production build/HTTP smoke, Android and iOS release compile checks, and the aggregate `release-gate`.
- `OpFin security monitoring`: locked web/API asset/PHP/Pub dependency checks, security and brand control checks, and the aggregate `security-gate`.
- `Deployment contract` for the existing Railway deployment contract.

Failed, cancelled, missing or incomplete checks are not a pass. Do not add `continue-on-error`, remove the audit, lower the severity threshold, or force a vulnerable package to make the pipeline green. A change to a security exception requires a documented rationale, owner, expiry and independent review. There are no blanket advisory suppressions in this implementation.

## Continuing controls

The security workflow runs for pull requests, main-branch pushes and daily at 03:17 UTC once it is on the default branch. It queries current advisories against locked versions, so new advisories can be detected even without a code change. Registry or advisory-service failures fail closed and require investigation rather than being reported as a clean scan.

Dependabot checks Composer, both npm projects, Pub and Actions daily; Android Gradle dependencies are checked weekly. Update pull requests are assigned to `lynelk` and require review and passing checks. Updates are not automatically deployed. Compatible updates may be grouped, but security findings are not ignored.

The web security floor rejects `sharp` versions below 0.35.4 and rejects a mismatch between its override and lockfile. The floor is an additional regression control, not a replacement for current vulnerability scans. The Flutter check sends only public package names and locked versions to OSV, handles pagination and blocks any returned advisory. It explicitly excludes SDK packages and does not claim to audit the Flutter engine, Android/iOS operating systems or every native transitive dependency.

## Runtime protections introduced with the launch hardening

- Per-response nonce-based production script policy, anti-framing, MIME sniffing prevention, constrained referrers, HTTPS enforcement and private/no-store HTML.
- Existing inline CSS attributes remain permitted for compatibility; arbitrary inline scripts and eval are not permitted by the production script policy.
- Next.js remote image fetching is closed by default; SVG optimisation, private-IP image fetches and redirect following are not enabled.
- Existing web session cookies remain HttpOnly, Secure in production and SameSite=Lax. Role cookies are navigation hints, never API authorisation evidence.
- Android explicitly disables cleartext traffic and excludes application data from cloud and device-to-device backups. Release API configuration must use HTTPS without embedded credentials.
- Sensitive session identifiers remain in secure storage. Local logout removes active session values while preserving the onboarding preference.
- API authorisation, tenant isolation, consent, affordability, financial finality, CPay-only money movement and regulated account-deletion rules remain backend responsibilities.

## Operational controls requiring owner verification

Code checks do not prove these settings are enabled. Verify and record them separately:

1. Protect `main` with required `release-gate`, `security-gate` and deployment-contract checks, reviewed pull requests, no force pushes and controlled administrator bypass. Restrict who can modify workflows and production deployments.
2. Enable repository vulnerability alerts, private vulnerability reporting, secret scanning and push protection where the account supports them. Review historic commits and rotate exposed credentials; current-index scanning does not purge history or revoke a key.
3. Use least-privilege, environment-scoped deployment and provider credentials; rotate them, review access and enable strong authentication for privileged operational accounts under the approved access policy.
4. Test backup restoration, alert delivery and incident response. Confirm production logging excludes credentials and unnecessary financial/identity data, with access control and retention limits.
5. Verify the actual deployed commit, public response headers, login/session behaviour, permission boundaries and critical financial journeys after deployment. Do not run live payments, loan creation or customer deletion as a smoke test.
6. Arrange independent security testing before general availability and after substantial changes to identity, authorisation, payment or lending functionality. Include rate limits, IDOR/tenant boundaries, session invalidation, uploads, webhooks and dependency exposure.

## Response ownership and procedure

The repository owner is accountable for assigning a security maintainer and backup. Review failed scheduled jobs and dependency alerts promptly. As proposed internal response targets, contain confirmed exploitation or exposed production credentials immediately; triage other high-severity findings within one working day and agree a remediation deadline based on exposure. These are operational targets, not an externally advertised service-level promise.

For an incident: preserve restricted evidence, contain the affected path, revoke or rotate compromised credentials, assess customer and regulatory impact with the responsible officers, fix and test, deploy through the protected process, and record the cause and follow-up actions. Do not disclose customer evidence in a public pull request.

## References

- Sharp advisory: https://github.com/advisories/GHSA-rgj7-g3m4-5g8c
- Next.js CSP: https://nextjs.org/docs/app/guides/content-security-policy
- Android backup: https://developer.android.com/identity/data/autobackup
- OSV API: https://google.github.io/osv.dev/api/
- Dependabot configuration: https://docs.github.com/en/code-security/dependabot/working-with-dependabot/dependabot-options-reference
