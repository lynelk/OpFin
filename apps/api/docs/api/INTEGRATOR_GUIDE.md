# OpFin API integrator guide

Review date: 18 September 2026. Audience: developers, technical support and partners. This guide explains the current repository contracts; it is not confirmation that a partner has production access.

## Understand what the API does

An API request asks OpFin to read information or perform one defined operation. Authentication establishes the caller; authorisation determines whether that caller may act on the requested record. A customer token must not give access to another customer's profile, application, wallet or receipt.

The API is authoritative for identity, consent, scoring, credit limits, loan state, payment outcomes and accounting. A client displays this state and submits authorised instructions. It must not fabricate provider scores, recalculate a separate credit limit or mark a pending payment as complete.

## Find the right operation

Start with [the task-oriented quick reference](API_QUICK_REFERENCE.md), then [current endpoint payloads](current-endpoints.md) and [the client contract](frontend-backend-contract.md).

```bash
# From repository root:
python3 scripts/search-api.py "wallet"
python3 scripts/search-api.py "credit"
python3 scripts/search-docs.py "disclosure" --api
```

The exact routes currently use `/api/...`. A base URL configured with `/api` must not receive that prefix twice. Do not invent a `/v1` path because an older design document proposed versioning.

The generated reference lists all non-demo registered `/api` operations, with the registration environment, source commit, handler and middleware. It reports gaps in the curated tables. Registration does not prove production availability, and middleware metadata does not disclose every ownership or capability check.

## Make a safe first request

With the local API running, liveness is a read-only first check:

```bash
curl --fail-with-body \
  -H 'Accept: application/json' \
  http://127.0.0.1:8000/api/health/live
```

The reviewed health controller returns the standard success envelope with `data.status` set to `ok` and `data.service` identifying the backend. This confirms that the process answers, not that lending, SMS, CPay, the worker or the scheduler is ready. Inspect the body of `/api/health/ready`: it can report integration blocking or warming operations even when the HTTP request succeeds.

Use a disposable test account and the authorised local/sandbox environment for any authenticated operation. Never paste real customer credentials, access tokens, identity documents or provider keys into tickets, screenshots or examples.

## Authentication and account creation

The current mobile journey is phone, OTP, names, six-digit PIN, then authenticated Home. Public authentication endpoints are throttled. The curated account contract describes these steps:

| Step | Operation | What to retain or check |
| --- | --- | --- |
| Request verification | `POST /api/generate-otp` | Approved delivery channel and retry state; no promise that an unconfigured gateway delivered a message |
| Verify the code | `POST /api/verify-otp` | Short-lived phone-verification token |
| Register | `POST /api/register` | Names, verified phone, PIN confirmation and required acceptance fields described in the endpoint contract |
| Sign in | `POST /api/login` | Authenticated session/token; legacy password compatibility is not the new mobile onboarding design |
| Read profile | `GET /api/profile` | The caller's sanitised profile, not private identity evidence |
| Sign out | `POST /api/logout` | Revoke the current token according to the handler contract |

Use the returned access token for the authenticated API flow. Keep it in the platform's approved secure storage and out of URLs and logs. Do not invent a refresh-token endpoint, token lifetime or universal re-authentication payload: inspect the current authentication contract, configuration and tests.

## Request and response rules

Send `Accept: application/json` for JSON API work. Use the content type required by the operation: ordinary JSON and multipart identity evidence are different payloads. KYC uses the documented fields `national_id`, `national_id_front`, `national_id_back` and `selfie_with_id`.

The curated first-party contracts describe a `success`, `message` and `data` envelope. Do not assume every framework exception, proxy failure or callback transport has exactly that shape. Check both HTTP status and the documented body, and handle malformed or unexpected responses safely.

Treat examples in [current endpoints](current-endpoints.md) as illustrative values, not IDs that exist in every environment. Obtain product, term, application, offer and wallet identifiers from authorised responses. Only send query, pagination, sorting or filtering parameters supported by that endpoint; no global pagination convention is certified by this guide.

Money fields ending in `_minor` are integer amounts in the configured currency unit. Do not use floating-point financial arithmetic or assume all currencies have two decimal places. The current CPay example configuration uses UGX and a minor-unit exponent of zero; integration code must still respect the actual configured currency contract.

## Apply, review, accept and repay

Read profile state and eligible options before submitting an application. The server validates the available amount and eligible term. A formal offer supplies the exact disclosures for acceptance, including its disclosure hash and required credit-information reporting consent.

For repayment, [the current contract](current-endpoints.md) requires an idempotency key in the supported header or body. One logical request uses one stable key. A retry of that request must not create a new logical payment or change the payload. Do not extend this rule by assuming every write endpoint supports the same header.

An accepted request, including HTTP 202 where specified, is not completed money movement. Display pending status until the authoritative outcome arrives. After a timeout, inspect the existing transaction/application state or use the supported recovery path before issuing a new money-changing request. Reconciliation, not repeated button presses, resolves ambiguous provider outcomes.

## Errors and recovery

| Situation | Client responsibility |
| --- | --- |
| Authentication rejected or expired | Use the supported sign-in/re-authentication flow; do not loop indefinitely |
| Operation forbidden | Explain access denial and use the approved escalation path; never retry with another person's credentials |
| Validation rejected | Show the documented field or business-rule message without altering the server's decision |
| Throttled request | Respect the returned retry guidance where provided; avoid rapid automatic retries |
| Network timeout or service failure | Preserve the user's state and request reference; determine whether the operation was accepted before retrying |
| Provider or capability unavailable | Show the actual unavailable/pending state; do not advertise a successful or activated service |
| Unexpected response | Fail safely, capture a non-sensitive diagnostic reference and escalate |

This table is a handling guide, not a claim that every endpoint uses identical error codes. Verify endpoint-specific statuses and bodies in the relevant handler and behavioural tests.

## Callbacks and privileged operations

CPay, WhatsApp and USSD callbacks have their own authentication, validation and replay controls. A callback route without customer-token middleware is not evidence that unsigned traffic is permitted. Review the corresponding controller, adapter, configuration and tests. Never run synthetic financial callbacks against production as a documentation exercise.

Administrative operations require their configured roles, permissions, ownership checks and governance controls. Do not infer “admin can do everything” from a route prefix. Test negative authorisation as well as the successful action.

## Contract completeness and change control

The generated route index is not a complete OpenAPI schema and must not be sold as a generated SDK contract. A fully documented operation needs its purpose, audience, availability, parameters, validation, examples, responses, errors, permissions, idempotency/retry semantics and source/test references.

Follow [documentation maintenance](../../../../docs/DOCUMENTATION_MAINTENANCE.md). Update current contracts in the same PR as the implementation; run the generated-table comparison; resolve narrative coverage gaps; and test the affected web/mobile/partner consumers. Keep capability-gated products, source implementation and verified release availability distinct.
