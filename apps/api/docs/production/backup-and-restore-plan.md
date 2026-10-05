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

Production runs on the Railway services and buckets approved in `ops/railway/topology-policy.json`. The owner approved two buckets on 5 October 2026: `OpFin Buck` for customer documents, and the `Postgres-PITR` bucket that Railway creates when point-in-time recovery is enabled. Any other new Railway service, volume or bucket still needs the owner's explicit approval first (see the Railway rules in `AGENTS.md`).

### What each layer protects against

| Failure | Data lost | Protection |
|---|---|---|
| Database container crashes or restarts | None: committed transactions are already on the volume | PostgreSQL durability |
| Bad deploy, bad migration, deleted rows | None before the chosen moment | Point-in-time recovery: restore to the second before the mistake |
| Database volume or host destroyed | About the last 30 seconds | Point-in-time recovery (continuous WAL archive in the bucket) |
| Railway account or region unavailable | Back to the last off-site copy | Weekly encrypted off-site copy (Step 5) |

Zero loss when the volume itself is destroyed needs a standby database that confirms every write before it counts (synchronous replication). That is not set up; it needs a separate decision on Railway's Postgres high-availability option or a managed database with synchronous standby, and its cost.

### Step 1: Store customer documents in the bucket

KYC photos and statement uploads are files, not database rows, and are not covered by database recovery. On the container's local disk they are lost on every redeploy and the worker and scheduler cannot read them.

1. In Railway, on each of `OpFin`, `opfin-worker` and `opfin-scheduler`, add these variables as references to the `OpFin Buck` bucket (use **Add variable reference** so no key is copied by hand):
   - `AWS_ACCESS_KEY_ID` → the bucket's `ACCESS_KEY_ID`
   - `AWS_SECRET_ACCESS_KEY` → the bucket's `SECRET_ACCESS_KEY`
   - `AWS_DEFAULT_REGION` → the bucket's `REGION`
   - `AWS_BUCKET` → the bucket's `BUCKET` (not `RAILWAY_BUCKET_NAME`)
   - `AWS_ENDPOINT` → the bucket's `ENDPOINT`
   - `AWS_USE_PATH_STYLE_ENDPOINT=false`, unless the bucket's **Credentials** tab says to use path-style URLs
2. On the same three services set `KYC_FILESYSTEM_DISK=s3` and `OPFIN_STATEMENT_DISK=s3`. Leave `FILESYSTEM_DISK` unchanged.
3. Deploy, then in the `OpFin` service shell run `php artisan opfin:storage-check`. It writes, reads back and deletes a probe file on both document disks and fails if either does not work. Do not take real customers' documents until it passes.
4. Files uploaded before this change were on the container disk and are likely already gone. Ask affected customers to upload their documents again.

The bucket is private. Never make it public; the API reads documents with its own credentials.

### Step 2: Turn on point-in-time recovery

1. In Railway, open the `Postgres OpFin` service, then **Settings**, and check that its source image is `ghcr.io/railwayapp-templates/postgres-ssl:18`. Point-in-time recovery needs Railway's image.
2. Open the **Backups** tab and choose **Enable PITR**. Railway creates the `Postgres-PITR` bucket, sets the archive variables and redeploys the database, which causes a short interruption. Do this in a quiet period.
3. On `Postgres OpFin`, add the variable `POSTGRES_ARCHIVE_TIMEOUT=30` and deploy. This forces a copy at least every 30 seconds even when the database is quiet (Railway's default is 60).
4. Wait until the Backups tab shows the restore range and date picker. That means the first base backup has finished.
5. Check health at least weekly, and after any Railway incident: `railway postgres pitr status --service "Postgres OpFin"`. If archiving is failing, fix it the same day: while it fails, the recovery window is not growing.

Railway keeps the last four weekly full backups, so the restore window is about four weeks. Storage and upload traffic are billed by Railway; check the cost against the workspace limits.

### Step 3: Keep the scheduled volume backups

On the same **Backups** tab, keep the daily, weekly and monthly schedule on, and take a manual backup (**Create backup**, or `railway postgres pitr backup create --service "Postgres OpFin" --name pre-migration`) before every deploy that runs a migration. These are a second, independent copy.

### Step 4: Restore after a mistake or incident

1. On the `Postgres OpFin` **Backups** tab, pick the moment just before the problem and choose **Restore to this moment**. Railway builds a new service next to the original and never touches the original.
2. Check the restored copy (connect with `railway connect`, inspect the affected rows).
3. To switch over: pause `opfin-worker` and `opfin-scheduler`, put the API in maintenance mode, point the API, worker and scheduler database variables at the restored service, deploy, then run `php artisan opfin:integrity-audit`.
4. Reconcile provider transactions after the restore point. Provider records are the evidence for any money that moved later. Never re-send a payment because it is missing from the restored data.
5. Enable PITR on the restored service if it becomes production, then remove the old service once the owner agrees. Record the incident.

### Step 5: Keep an encrypted off-site copy

Everything above lives inside Railway. At least weekly, keep a copy the owner controls elsewhere:

1. Install the Railway CLI and PostgreSQL 18 client tools.
2. Run `railway login`, then `railway link` and choose the `OpFin` project and `production` environment.
3. If the `Postgres OpFin` service has a public TCP proxy (`DATABASE_PUBLIC_URL`), from a folder outside the repository run:
   `railway run --service "Postgres OpFin" -- sh -c 'pg_dump "$DATABASE_PUBLIC_URL" --format=custom --no-owner --file=opfin-YYYY-MM-DD.dump'`
   If it has no TCP proxy, do not enable one only for this; ask for an approved off-site method instead.
4. Encrypt the file at once (for example a 7-Zip archive with AES-256 and a long passphrase kept in the password manager), delete the unencrypted dump, and store the encrypted copy outside Railway.
5. Never put a dump inside the repository folder, in a GitHub Actions artifact or in an issue. The repository is public.

### Step 6: Restore drill

Before launch and then every quarter:

1. Restore a recent point in time (Step 4, first part only) to a temporary service. This creates a Railway service for the drill: the owner approves it at the time and deletes it the same day.
2. Do not connect the API, worker or scheduler to it. In a local copy of `apps/api` with `APP_ENV=local` and every provider credential (SMS, mobile money, CPay, Cito, KYC, CRB) left empty, point `DB_*` at the restored service and run `php artisan migrate:status` and `php artisan opfin:integrity-audit`.
3. Compare row counts for `users`, `loans`, `transactions`, the ledger tables and `audit_logs` with production at the restore time.
4. Record the date, restore target, start and end times, results and problems in the operational readiness evidence, then delete the temporary service.
