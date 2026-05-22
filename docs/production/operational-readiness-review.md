# Operational Readiness Review

Date: 2026-05-22

## Summary

OpFin has initial operational foundations: health check, queue configuration, scheduler route, reconciliation records, support cases, compliance report records, and admin operations screens. It is not ready for live replacement until toolchain verification, monitoring, provider operations, backup, and incident runbooks are completed.

## Current Readiness

| Area | Status | Notes |
| --- | --- | --- |
| Health checks | Partial | `/api/health` exists and Laravel `/up` is configured. |
| Queues | Partial | Database queue is configured, SMS jobs exist, but worker operations are not verified. |
| Scheduler | Partial | Console schedule checks transaction status every minute, but production safety and provider behavior need review. |
| Logging | Partial | Laravel logging configured; `.env.example` now defaults to `LOG_LEVEL=info`. |
| Monitoring hooks | Missing/partial | No confirmed uptime, queue, payment, ledger, or provider alerting. |
| Backups | Missing/partial | Backup strategy is documented as required but not implemented here. |
| Error reporting | Missing/partial | No confirmed Sentry/Bugsnag/etc. integration. |
| Admin support workflows | Partial | Support cases, notes, reconciliation, compliance reports, and ledger view exist, but full support console is incomplete. |
| Provider operations | Partial/high risk | MTN/Airtel adapter structures exist, but production certification is not complete. |

## Fixed in this pass

- Added route throttling for auth, authenticated API routes, and webhooks.
- Removed duplicate manual API route registration to avoid duplicated route behavior.
- Added normalized API exception responses.
- Hardened frontend login/session cookie behavior.

## Critical / High-Risk Gaps

| Area | Risk | Required action |
| --- | --- | --- |
| Toolchain | Tests/builds cannot run locally. | Restore PHP, Composer, Pint, npm, and run CI locally or in GitHub Actions. |
| Queue operations | Workers and failed-job handling are not verified. | Define worker process, retry policy, failed-job alerting, and queue dashboards. |
| Scheduler operations | Scheduled jobs may call legacy provider flows. | Review scheduler commands and ensure no unsafe live calls outside approved adapters. |
| Monitoring | No production alert evidence. | Add uptime, API error, queue backlog, failed payment, ledger imbalance, and provider callback alerts. |
| Backups | No backup/restore rehearsal evidence. | Define database backup schedule and run restore drill. |
| Incident response | No incident runbook evidence. | Add runbooks for payment outage, provider callback failure, ledger mismatch, auth outage, and rollback. |
| Admin support | Customer lookup, payment exception handling, and identity verification are incomplete. | Complete support console before live replacement. |

## Cutover Requirement

Before cutover:

1. Health checks must be monitored.
2. Queue workers must run under process supervision.
3. Scheduler must be reviewed and monitored.
4. Backup and restore must be rehearsed.
5. Provider callback monitoring must be active.
6. Support and operations users must complete UAT.
