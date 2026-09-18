# Google Play account deletion configuration

## Canonical deletion paths

OpFin supports both deletion paths required for an Android app that permits account creation:

- **In-app:** `More` → `Privacy & account` → `Delete account`.
- **External web resource:** `https://opfin-web-production.up.railway.app/account/delete`.

The external resource must remain reachable without requiring the Android app to be installed. An unauthenticated visitor can load the deletion-specific page, review what deletion does, and sign in on the web solely to verify account ownership before submitting the deletion request.

## Play Console value

In Google Play Console, use this URL for the account-deletion web resource in the Data safety / account deletion section:

`https://opfin-web-production.up.railway.app/account/delete`

If OpFin later moves the public web experience to a verified custom domain, update this document and the Play Console field together. Do not leave the Play listing pointing at a retired Railway or custom-domain URL.

## Behaviour

A verified deletion request calls `DELETE /api/account`.

- When no active regulated or financial obligation exists, OpFin revokes active consents, removes optional customer context, invalidates access tokens, anonymizes identifiers that do not need to be retained, and deletes the active user account.
- When an active obligation must first be closed safely, OpFin records an `account_deletion` support case and returns a case number. The request remains recorded for completion through the regulated closure process.
- Financial, KYC/AML, credit-reporting, accounting, reconciliation, dispute, fraud-prevention and audit evidence may be retained only where law, regulation, security or another legitimate legal obligation requires retention. Retention must not keep a deleted account active.

## Release verification

Before submitting or updating the Google Play Data safety form:

1. Confirm the public deletion URL loads successfully in a logged-out browser.
2. Confirm the page names OpFin and prominently offers account deletion.
3. Confirm the web sign-in flow returns to `/account/delete` and does not send the user back to the Android app.
4. Confirm a test customer can complete `DELETE /api/account` with the correct 6-digit PIN (or legacy password for a migrated account) and `DELETE` confirmation.
5. Confirm the same endpoint rejects an incorrect PIN or legacy password.
6. Confirm accounts with active obligations receive a recorded pending-deletion case instead of silent deactivation.
7. Confirm retained-data disclosures remain consistent with the current privacy policy and actual backend behaviour.

The Play Console setting is external account configuration and is not embedded in the Android manifest or AAB. Repository changes can make the URL compliant, but the live URL must also be deployed and entered in Play Console before submission.
