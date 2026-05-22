# Provider Callback Runbook

Date: 2026-05-22

This runbook covers mobile money provider callbacks and payment status events.

## Scope

Provider callbacks include:

- disbursement status updates;
- repayment collection status updates;
- reversal results;
- failed transaction notifications;
- duplicate or delayed webhook events.

## Required Controls

- Provider signatures must be validated before processing.
- Each event must have a provider reference and internal idempotency key.
- Duplicate events must not create duplicate ledger entries.
- Callback processing must not directly mutate loan balances outside ledger services.
- Failed callbacks must be reviewable by operations.
- Sandbox and production provider modes must be clearly separated.

## Callback Processing Checklist

1. Validate provider configuration and environment.
2. Validate request signature.
3. Normalize provider payload into internal format.
4. Check idempotency key and provider reference.
5. Store raw safe metadata needed for audit and reconciliation.
6. Create or update mobile money transaction status.
7. Route successful financial events through the ledger service.
8. Create audit log for state change.
9. Flag exceptions for reconciliation review.

## Duplicate Event Handling

If a duplicate callback arrives:

- return an accepted response where appropriate;
- do not create a second ledger entry;
- do not change final status unless a valid state transition exists;
- record duplicate detection metadata;
- include the event in reconciliation evidence.

## Failed Callback Handling

| Failure | Action |
| --- | --- |
| Invalid signature | Reject, log security event, do not process state. |
| Unknown provider reference | Store as exception, do not mutate loan state. |
| Conflicting status | Escalate to reconciliation queue. |
| Processing error | Store failed job/event and retry only after idempotency review. |
| Provider outage | Pause unsafe retries and use provider status lookup. |

## Reconciliation Requirements

Provider records must be compared against:

- internal mobile money transaction records;
- ledger entries;
- loan account status;
- repayment schedule status;
- audit trail.

Any mismatch must remain open until reviewed and signed off by operations or finance.

## Cutover Gate

Before production replacement:

- production provider webhook credentials must be configured securely;
- callback endpoint must be monitored;
- duplicate callback test must pass in staging;
- failed callback test must create a reviewable exception;
- reconciliation report must match provider sample data.
