# Incident Response Runbook

Date: 2026-05-22

This runbook defines production incident response for OpFin.

## Severity Levels

| Severity | Definition | Examples |
| --- | --- | --- |
| Critical | Customer, financial, compliance, or audit impact is active or likely. | ledger imbalance, duplicate payment posting, auth outage, audit logging failure |
| High | Major workflow or operational dependency is degraded. | provider callback outage, queue worker failure, backup failure |
| Medium | Partial degradation with workaround available. | elevated validation errors, admin report delay |
| Low | Minor issue with limited operational impact. | non-critical UI defect |

## Response Steps

1. Declare severity and owner.
2. Start an incident record with time, reporter, affected systems, and current impact.
3. Stop unsafe automation if financial state may be affected.
4. Preserve logs, provider references, idempotency keys, audit records, and database evidence.
5. Communicate status to support, operations, finance, and compliance as needed.
6. Apply the smallest safe mitigation.
7. Validate recovery using health, ledger, payment, reconciliation, and audit checks.
8. Record root cause, timeline, customer impact, and follow-up actions.

## Financial Incident Rules

- Do not delete ledger, payment, audit, or reconciliation records.
- Do not re-run provider callbacks manually without idempotency review.
- Do not issue manual credits or reversals without approval and audit evidence.
- Do not close the incident until reconciliation and ledger checks pass.

## Common Incidents

### Ledger Imbalance

1. Stop financial posting jobs if imbalance is active.
2. Identify affected transaction, loan account, ledger account, and actor.
3. Check recent deployments and failed jobs.
4. Compare ledger entries to payment transaction and loan state.
5. Correct only through approved reversal/correction flow.
6. Require finance and compliance sign-off.

### Duplicate Payment Posting

1. Stop affected payment processing path.
2. Identify idempotency key, provider reference, webhook event, and ledger transaction.
3. Confirm whether customer balance or schedule was affected.
4. Apply approved reversal if needed.
5. Add regression test before re-enabling path.

### Provider Callback Outage

1. Confirm whether provider callbacks are delayed or rejected.
2. Check signature validation, endpoint availability, and provider status.
3. Pause risky retries if duplicate events may arrive later.
4. Reconcile provider status lookup against internal records.
5. Resume processing only after idempotency and reconciliation checks pass.

### Authentication Outage

1. Confirm scope: customer, admin, or all users.
2. Check API health, rate limits, token issuance, and session handling.
3. Protect admin routes and disable unsafe shortcuts.
4. Communicate customer/support impact.
5. Validate login, profile, and logout after recovery.

## Post-Incident Review

Every Critical or High incident requires:

- timeline;
- root cause;
- affected customers and financial records;
- evidence preserved;
- corrective actions;
- tests or controls added;
- owner and due date;
- compliance review where required.

## Cutover Gate

Before production replacement, incident response must be rehearsed for:

- failed payment callback;
- duplicate payment event;
- ledger imbalance;
- queue worker failure;
- authentication outage;
- rollback decision.
