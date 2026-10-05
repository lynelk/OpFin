# Backup and Restore Plan

Date: 2026-05-22 (Railway runbook added 2026-10-05)

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

## Railway runbook (October 2026)

Production runs on the approved Railway services in `ops/railway/topology-policy.json`: `Postgres`, `OpFin` (API), `opfin-web`, `opfin-worker` and `opfin-scheduler`. The steps below use only those services and the owner's own computer. A new Railway service, volume or bucket needs the owner's explicit approval of that specific change first (see the Railway rules in `AGENTS.md`).

### Step 1: Check where uploaded files are stored

KYC photos and uploaded statements are files, not database rows. Database backups do not include them.

1. In Railway, open the `OpFin` service, then **Variables**. Note `FILESYSTEM_DISK` and `KYC_FILESYSTEM_DISK`. Uploaded statement PDFs always use the `local` disk: `statement_disk` is fixed in `config/financial_intelligence.php`.
2. Where a disk is `local` (the default for all three), those files live on the container's disk. Unless the `OpFin` service has a volume attached at `storage/app`, they are lost on every redeploy, and the worker and scheduler cannot read them.
3. If that is the case, treat it as a launch blocker. The fix is a decision for the owner: either approve a volume on the `OpFin` service, or configure an S3-compatible bucket (the `s3` disk) and approve that change. Record the decision before taking KYC documents from real customers.

### Step 2: Turn on Railway's scheduled database backups

This uses a feature of the existing `Postgres` service. It does not create a new service.

1. In Railway, open the `Postgres` service in the `production` environment, then the **Backups** tab.
2. Choose **Edit schedule** and turn on the daily, weekly and monthly backups. Note the retention Railway shows for each.
3. Choose **Create backup** to take one straight away.
4. Backup storage is billed by Railway. Check that the cost fits within the workspace limits in `ops/railway/topology-policy.json`.

These backups stay inside Railway. They protect against a bad deploy, a bad migration or deleted data, not against losing access to the Railway account.

### Step 3: Take a manual backup before risky changes

Take a Railway backup (**Backups**, then **Create backup**) before every deploy that runs a migration, and before any bulk data change. Note the backup's time in the release record.

### Step 4: Keep an encrypted off-site copy

Do this at least weekly until an automated off-site copy is approved.

1. Install the Railway CLI and PostgreSQL client tools whose version is at least the server's (`SELECT version();`).
2. Run `railway login`, then `railway link` and choose the `OpFin` project and `production` environment.
3. Check whether the `Postgres` service has a public TCP proxy (it has `DATABASE_PUBLIC_URL`). If it does not, do not enable one only for this; use the Railway backups and ask for an approved off-site method instead.
4. From a folder outside the repository, run:
   `railway run --service Postgres -- sh -c 'pg_dump "$DATABASE_PUBLIC_URL" --format=custom --no-owner --file=opfin-YYYY-MM-DD.dump'`
5. Encrypt the file at once (for example a 7-Zip archive with AES-256 and a long passphrase kept in the password manager), delete the unencrypted dump, and store the encrypted copy on storage the owner controls outside Railway.
6. Never put a dump inside the repository folder, in a GitHub Actions artifact or in an issue. The repository is public.

### Step 5: Run a restore drill

Run one before launch and then every quarter. Restore into a throwaway database on the owner's computer, not into production and not into a new Railway service.

1. Start an empty local PostgreSQL database (a local installation or a container).
2. Restore: `pg_restore --no-owner --dbname=<local database URL> opfin-YYYY-MM-DD.dump`.
3. In a local copy of `apps/api`, point `DB_*` at the local database. Set `APP_ENV=local` and leave every provider credential (SMS, mobile money, CPay, Cito, KYC, CRB) empty, so the restored data cannot message customers or move money. Do not start the worker or scheduler against restored data.
4. Run `php artisan migrate:status` (nothing pending), then `php artisan opfin:integrity-audit`.
5. Compare row counts for `users`, `loans`, `transactions`, the ledger tables and `audit_logs` with production at the backup time.
6. Record the date, backup used, start and end times, results and problems in the operational readiness evidence, then delete the local database and the decrypted dump.

To test a Railway backup itself, restoring it in the production service rewinds production data. Only do that in an incident, after taking a fresh manual backup.

### Point-in-time recovery

Railway's template PostgreSQL has no built-in point-in-time recovery. With daily backups, up to 24 hours of transactions could be lost. Manual backups before releases narrow that gap but do not close it. Real point-in-time recovery needs continuous WAL archiving to storage outside the database (for example pgBackRest or WAL-G with a bucket), or a managed PostgreSQL that provides it. Either is an infrastructure change that needs the owner's approval and cost review. Until then, the recovery point objective in the table above must be agreed knowing this limit.

### Restoring production after an incident

1. Stop writes: pause `opfin-worker` and `opfin-scheduler`, then put the API into maintenance mode.
2. Take a manual backup of the current state, even if it is damaged.
3. Restore the chosen Railway backup from the **Backups** tab and deploy the change.
4. Run `php artisan opfin:integrity-audit` on the API service.
5. Reconcile provider transactions made after the backup time. Provider records are the evidence for any money that moved after the backup. Never re-send a payment because it is missing from the restored data.
6. Resume the scheduler and worker, leave maintenance mode, and record the incident.
