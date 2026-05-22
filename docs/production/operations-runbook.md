# Production Operations Runbook

Date: 2026-05-22

This runbook defines the minimum daily and incident operations required before OpFin replaces the current live system.

## Operating Principles

- Treat OpFin as a regulated financial system, not a general web application.
- Do not manually change balances, repayment schedules, ledger entries, or payment statuses outside approved services and audited admin workflows.
- Keep mock, sandbox, and production provider modes clearly separated.
- Escalate any ledger imbalance, duplicate payment, provider mismatch, or missing audit log as a production incident.
- Record every privileged operational action with user, role, reason, timestamp, affected entity, and before/after state where available.

## Daily Checks

| Check | Owner | Evidence |
| --- | --- | --- |
| API health endpoint is reachable. | Engineering/on-call | Monitoring dashboard or health-check log |
| Queue workers are running. | Engineering/on-call | Process supervisor status and queue metrics |
| Failed jobs are reviewed. | Engineering/on-call | Failed-job report |
| Scheduler ran expected jobs. | Engineering/on-call | Scheduler logs |
| Mobile money callbacks are arriving. | Operations | Provider callback dashboard |
| Reconciliation exceptions are reviewed. | Operations | Reconciliation queue |
| Ledger imbalance report is clean. | Finance/operations | Ledger integrity report |
| Admin audit trail has no unexplained privileged actions. | Compliance | Audit review sample |
| Backup job completed. | Engineering/on-call | Backup log |

## Shift Handover

Each handover must include:

- open incidents and severity
- failed provider transactions awaiting action
- unresolved reconciliation differences
- failed jobs older than one hour
- pending support escalations
- compliance reports due
- any manual workaround used during the shift

## Access Control

- Production admin access must be limited to named users.
- Shared admin accounts are not allowed.
- Access reviews must be completed before cutover and monthly after cutover.
- Privileged access changes must be logged and approved.
- Emergency access must expire and be reviewed after use.

## Financial Operations Rules

- Never edit financial database records directly.
- Never mark a payment successful based only on a customer screenshot.
- Never retry a disbursement or collection without checking idempotency status.
- Never close a reconciliation exception without linking supporting evidence.
- Never apply reversals without a matching audit reason and ledger transaction.

## Escalation Matrix

| Event | Severity | First owner | Escalation |
| --- | --- | --- | --- |
| Authentication outage | Critical | Engineering/on-call | Product owner, support lead |
| Ledger imbalance | Critical | Engineering/on-call | Finance lead, compliance lead |
| Duplicate payment posting | Critical | Engineering/on-call | Finance lead, provider manager |
| Provider callback outage | High | Operations | Engineering/on-call, provider manager |
| Failed disbursement spike | High | Operations | Finance lead, engineering/on-call |
| Backup failure | High | Engineering/on-call | Engineering lead |
| Audit logging failure | Critical | Engineering/on-call | Compliance lead |

## Cutover Gate

This runbook is ready for cutover only after:

1. owners are assigned for every daily check;
2. monitoring dashboards exist for the checks above;
3. queue, scheduler, backup, callback, and incident runbooks are rehearsed;
4. support and operations users complete UAT;
5. rollback steps are tested against a non-production environment.
