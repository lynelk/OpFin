# Backup and Restore Plan

Date: 2026-05-22

This plan defines backup and restore expectations for OpFin before replacing the current live system.

## Backup Scope

Backups must cover:

- application database;
- audit logs;
- ledger records;
- payment/mobile money transaction records;
- reconciliation records;
- support cases and notes;
- compliance reports;
- user, role, and permission data;
- environment configuration inventory, excluding secret values;
- uploaded documents or KYC evidence if stored outside the database.

## Backup Requirements

| Requirement | Minimum expectation |
| --- | --- |
| Frequency | At least daily full backup and shorter recovery-point coverage for transactional data. |
| Retention | Defined by regulatory and business requirements. |
| Encryption | Encrypted in transit and at rest. |
| Access | Limited to approved operations/engineering owners. |
| Integrity | Backups must be automatically checked. |
| Restore drill | Must be performed before cutover and on a recurring schedule. |

## Restore Drill

1. Select a recent backup.
2. Restore it into an isolated non-production environment.
3. Verify migrations and application boot.
4. Validate user records, loan records, payment records, ledger entries, audit logs, and support cases.
5. Run ledger integrity checks.
6. Run reconciliation checks against a known sample.
7. Confirm sensitive data remains protected.
8. Record start time, end time, owner, issues, and recovery result.

## Recovery Targets

Recovery point objective and recovery time objective must be agreed before cutover. Until signed off, production replacement remains blocked.

| Area | Target owner | Status |
| --- | --- | --- |
| Customer and loan data recovery point | Product/operations | Requires sign-off |
| Payment and ledger recovery point | Finance/engineering | Requires sign-off |
| Maximum restore time | Product/engineering | Requires sign-off |
| Support operations continuity | Support lead | Requires sign-off |

## Backup Failure Response

1. Treat a missed backup as high severity.
2. Confirm whether transactional data is still protected by replicas or point-in-time recovery.
3. Fix the backup pipeline.
4. Run a replacement backup.
5. Notify engineering lead and operations lead.
6. Record the incident and corrective action.

## Cutover Gate

Before production replacement:

- backup schedule must be active;
- backup storage must be access-controlled;
- at least one restore drill must pass;
- recovery targets must be approved;
- backup and restore evidence must be attached to the cutover checklist.
