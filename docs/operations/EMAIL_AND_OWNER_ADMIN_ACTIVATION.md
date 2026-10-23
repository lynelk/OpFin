# OpFin: owner administrator and production email activation

Status: implementation candidate; **not an assertion of production activation**.  
Scope: OpFin alone, existing Railway project and services.  
Reviewed: 19 October 2026. Language: English (United Kingdom).

## Security and deployment boundary

The production application has a staff email recovery flow, but a reset code is not proof of email delivery merely because a queued job is accepted. In production, the application must refuse to queue a reset code if the configured mailer would write it to logs or lacks the required configured sender/credential. The provider must be verified separately.

Railway Free/Trial/Hobby environments block outbound SMTP. Prefer the **Resend HTTPS API** (Laravel `resend` transport with `resend/resend-php`), which works over HTTPS without creating any new Railway service. SMTP is only an option where plan/network and provider configuration permit it. Do not set mailer `log` or `array` for production.

## Provision the initial owner administrator

Use an **interactive OpFin API service shell** in the existing production Railway project. Access should be limited to the authorised owner. Do not create the owner using migrations, seeders, a default password, a public endpoint, a GitHub Actions workflow or a repository-stored secret.

```bash
php artisan opfin:admin:bootstrap \
  --email=<confirmed-owner-email> \
  --phone=<confirmed-owner-phone> \
  --first-name=<confirmed-first-name> \
  --last-name=<confirmed-last-name>
```

The command normalises the phone, refuses conflicting account identities, and prompts for a unique one-time password twice through hidden terminal input. The password must contain mixed case, a number and a symbol (8 or more characters); the administrator must replace it on first sign-in with a new password of 12 or more characters. The command is idempotent for an existing platform administrator; no password is provided as an argument or environment variable.

Verify in the application, not by exporting a database record: sign in at `/admin-login` with the staff email and temporary password, follow the mandatory password-change flow, and confirm `/admin/dashboard` opens with the expected `platform_admin` permissions. Confirm the audit trail reflects the bootstrap and password change.

## Configure transactional email

1. Provision a transactional email provider account under the authorised OpFin organisation, obtain the **production API key** through its secure dashboard, and verify a sender domain the organisation controls (SPF, DKIM and applicable DMARC). No domain, credential or account ownership is assumed here.
2. On **both** existing Railway production services, `OpFin` (API) and `opfin-worker`, securely set:
   - `MAIL_MAILER=resend`
   - `RESEND_API_KEY=<provider-generated-secret>`
   - `MAIL_FROM_ADDRESS=<verified-sender-on-owned-domain>`
   - `MAIL_FROM_NAME=OpFin`
3. Keep both services' existing shared encryption, queue and database settings consistent; do not create extra services or disable existing queues. Never write `RESEND_API_KEY` to source, logs, tickets or chat.
4. Redeploy through the normal controlled release pipeline, with candidate-specific tests and verification. Do not claim functionality from variable presence alone.
5. Request a real code for a verified, provisioned staff account via `/forgot-password`. Check receipt in that inbox, sender authentication, five-minute expiry, maximum attempts, successful password change, rejection of reused codes and session revocation. Test an unknown email with no account disclosure; test a non-staff email with no message sent.
6. Review queue failures, delivery-provider rejection/bounce logs, web/API error rates and the audit trail **without logging codes**. Test a provider-outage scenario before acceptance.

## Release and operational acceptance checklist

- [ ] Owner account provisioned interactively and verified with a successful first-login password change.
- [ ] Verified sender domain and production transactional API key present.
- [ ] API and worker both configured with the exact same transport and sender.
- [ ] Reviewed source change is merged and deployed only after full release gates.
- [ ] Real email received and a genuine staff password reset completed.
- [ ] OTP confidentiality, one-use, expiry, login/session rules and failed-mail behaviour accepted.
- [ ] Monitoring and rollback instructions recorded, with no extra infrastructure.

Until all checks are complete, **do not mark email recovery operational or the owner account provisioned**. SMS recovery may be an independent fallback only after a real authorised phone delivery test.
