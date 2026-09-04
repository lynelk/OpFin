# Queue and Scheduler Runbook

Date: 2026-05-22

This runbook covers background jobs, failed jobs, and scheduled tasks for OpFin production replacement.

## Required Production Setup

- Run queue workers under a process supervisor.
- Use a dedicated queue for payment/provider work where supported.
- Store failed jobs and expose them to engineering/on-call.
- Configure scheduler execution once per minute on one authoritative runtime.
- Send queue and scheduler logs to centralized logging.
- Alert on worker downtime, failed jobs, and scheduler silence.

## Worker Checks

| Check | Expected result | Response if failed |
| --- | --- | --- |
| Worker process is running. | All configured workers healthy. | Restart worker, inspect logs, escalate if repeated. |
| Queue depth is within threshold. | No sustained backlog above agreed limit. | Scale workers or pause non-critical jobs. |
| Failed jobs are zero or explained. | No unexplained failed financial/provider jobs. | Triage before retrying. |
| Retry count is normal. | No repeated retry loop for the same entity. | Stop affected job class and investigate. |

## Failed Job Triage

1. Identify job class, payload, queue, attempts, and exception.
2. Determine whether the job can affect financial state.
3. Check whether an audit log or ledger entry already exists.
4. Check idempotency key and provider reference before retrying.
5. Retry only if the job is idempotent and the external state is understood.
6. Record the action in the incident or support case.

## Scheduler Checks

| Scheduled area | Required check |
| --- | --- |
| Provider status checks | Must not call live providers unless production credentials and provider mode are approved. |
| Reconciliation checks | Must create reviewable exceptions, not silent balance changes. |
| Compliance reporting | Must produce traceable report records. |
| Notifications | Must not send duplicate customer messages after retry. |

## Unsafe Conditions

Stop or pause affected workers when:

- ledger entries are imbalanced;
- provider callbacks are duplicated without idempotency protection;
- failed jobs repeatedly mutate the same financial entity;
- sandbox provider mode is active in production;
- scheduler is making unapproved live provider calls;
- audit logging is unavailable for sensitive actions.

## Restart Procedure

1. Confirm no migration or deployment is in progress.
2. Check current queue depth and failed-job count.
3. Restart workers through the process supervisor.
4. Confirm workers are processing fresh jobs.
5. Review the first financial/provider jobs processed after restart.
6. Log restart time, reason, owner, and outcome.

## Cutover Gate

Before production replacement:

- worker supervision must be installed;
- failed-job storage must be verified;
- alert thresholds must be configured;
- a failed payment job must be replayed successfully in staging;
- scheduler silence and duplicate scheduler execution must be tested.
