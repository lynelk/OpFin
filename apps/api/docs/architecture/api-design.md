# API design

## API style

The OpFin API is JSON-first for application clients, with multipart upload only where required for private identity evidence. Financial and identity state is server-authoritative.

## Response envelope

Success:

```json
{"success": true, "message": "Result", "data": {}}
```

Failure:

```json
{"success": false, "message": "Safe explanation", "errors": {}}
```

HTTP status remains authoritative.

## Authentication

The launch account journey is phone verification followed by a six-digit PIN. OTP generation/verification is throttled and PIN login is rate-limited. The register response includes a bearer token so the customer can continue without a redundant second login.

Legacy password fields remain compatibility inputs only; new clients must use the PIN contract.

## Customer state aggregation

`CustomerCreditProfileService` is the main borrower-state aggregator. It joins:

- verified identity;
- active credit consent;
- CRB/MNO/third-party/internal score components;
- profile credit limit;
- current exposure;
- due/outstanding schedule state;
- optional second phone;
- active loan;
- next customer action.

Clients should not reproduce this logic.

## External adapters

`ExternalScoringService` connects configured CRB/MNO/third-party sources. Each source has explicit status, score, weight, reason codes, received/expiry timestamps and reference where available.

`IdentityVerificationService` connects the configured identity provider and records NIN, liveness, face-match and NIN/phone-link outcomes.

Unavailable adapters produce unavailable/error/pending states. They do not generate synthetic success.

## Wallets and money movement

Verified phone numbers can map to customer wallets. Defaults are separate for disbursement and repayment. Limit remains profile-level.

Offer acceptance and repayment may select a verified wallet ID. Backend ownership checks prevent use of another customer's wallet. Provider finality, ledger and reconciliation remain separate from API acknowledgement.

## Cross-channel entry points

- Flutter/web use authenticated REST.
- WhatsApp webhook validates Meta signatures; text and KYC image messages enter `WhatsAppJourneyService`.
- USSD callback uses the same profile service and can require an aggregator shared secret.
- Image/regulated commitment steps are handed to an authenticated/high-assurance path rather than approximated in USSD.

## Idempotency

Repayment initiation requires an idempotency key. Offer/disbursement code retains its existing offer/version/idempotency controls. Retries must never create a second economic event.

## Privacy

NIN is masked in normal profile responses. Private KYC image paths and raw provider evidence are not customer-facing fields. Production evidence storage is configurable and should be persistent/private.
