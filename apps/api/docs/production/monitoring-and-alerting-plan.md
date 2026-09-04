# Monitoring and Alerting Plan

Date: 2026-05-22

This plan defines the monitoring coverage required before OpFin can replace the current live system.

## Required Signals

| Area | Signal | Alert threshold |
| --- | --- | --- |
| Availability | `/api/health` and web availability | Any sustained failure over agreed window |
| Authentication | login failures, token errors, password reset failures | spike above baseline |
| API errors | 4xx/5xx by endpoint and role | 5xx spike or critical endpoint failure |
| Queues | worker status, queue depth, failed jobs | worker down, backlog, failed financial job |
| Scheduler | last successful scheduler tick | missed execution window |
| Mobile money | callback volume, failed callbacks, signature failures | callback drop, high failure rate |
| Payments | failed disbursements, failed collections, duplicates | any unexplained spike |
| Ledger | imbalance checks, orphan postings, reversal errors | any imbalance |
| Reconciliation | unmatched provider/internal transactions | aged exception or spike |
| Audit logging | failed audit writes, missing audit coverage | any failed audit write |
| Compliance | report generation failures | missed report deadline |
| Backups | backup success and restore drill result | failed backup or stale restore evidence |

## Dashboard Requirements

Production dashboards must show:

- current API health and response time;
- authentication failures;
- queue depth and failed jobs;
- scheduler status;
- provider callback activity;
- payment success/failure counts by provider;
- reconciliation exception counts and age;
- ledger integrity status;
- open support exceptions;
- backup status.

## Alert Routing

| Severity | Examples | Notify |
| --- | --- | --- |
| Critical | ledger imbalance, auth outage, audit logging failure, duplicate payment posting | engineering/on-call, finance lead, compliance lead |
| High | provider callback outage, queue worker down, backup failure | engineering/on-call, operations lead |
| Medium | elevated validation errors, aged support cases, reconciliation backlog | operations lead, product owner |
| Low | non-critical report delay, minor dashboard anomaly | owning team |

## Evidence Required Before Cutover

- Screenshots or links for active dashboards.
- Test alert for health-check failure.
- Test alert for queue worker failure.
- Test alert for failed provider callback.
- Test alert for ledger imbalance simulation.
- Backup alert evidence.
- Named on-call routing and escalation contacts.

## Current Status

Monitoring requirements are defined, but active dashboards and alert evidence are still required before production replacement.
