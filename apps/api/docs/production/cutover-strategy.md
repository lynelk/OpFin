# Cutover Strategy

Date: 2026-05-22

This strategy describes the final move from the current live system to the new OpFin build.

## Cutover Readiness Checklist

- [ ] Critical blockers are resolved.
- [ ] Replacement feature scope is approved.
- [ ] Current live system discovery is complete.
- [ ] Migration dry run has passed in staging.
- [ ] Parallel run has passed acceptance thresholds.
- [ ] Backend tests, migrations, lint/format/static checks pass.
- [ ] Frontend tests, typecheck, lint, and build pass.
- [ ] MTN/Airtel provider sandbox or certification evidence is complete.
- [ ] KYC and CRB provider integrations are approved.
- [ ] Ledger/trial balance reconciliation is signed off.
- [ ] Compliance reports are approved.
- [ ] Support operations UAT is signed off.
- [ ] Production monitoring, logs, alerts, and backups are active.
- [ ] Rollback plan is rehearsed.
- [ ] Customer and staff communications are approved.

## Freeze Window

Set a freeze window long enough to complete final sync, validation, routing changes, and smoke testing.

During freeze:

- Stop non-essential admin changes in the old system.
- Stop product/term configuration changes.
- Queue or pause new loan disbursements if operationally possible.
- Keep repayment collection handling explicitly assigned to one system.
- Record any unavoidable live-system activity in a cutover delta log.
- Keep provider callback handling monitored throughout.

## Final Data Sync

1. Take final live-system backup or export snapshot.
2. Export all delta records since the last dry run.
3. Import users, profiles, KYC, consent, products, applications, loans, schedules, payments, ledger, audit, reports, and config deltas.
4. Reconcile record counts.
5. Reconcile active loan balances.
6. Reconcile repayment schedules.
7. Reconcile mobile money pending/success/failed transactions.
8. Reconcile ledger opening balances.
9. Produce final migration manifest and exception list.
10. Obtain sign-off before routing customer traffic.

## Routing and Access Change

### DNS or application routing

- Point customer web/mobile API traffic to the new backend only after validation sign-off.
- Ensure frontend environment variables point to the production backend URL.
- Disable mock mode and demo shortcuts in production.
- Keep old system accessible to authorized admins as read-only if rollback strategy requires it.

### Provider callbacks

- Switch MTN/Airtel callback URLs only during the approved window.
- Confirm webhook signature validation and replay protection are enabled.
- Send provider test callbacks where available.
- Monitor callback receipt, response codes, and reconciliation status.

### Admin access switch

- Enable admin users in the new system.
- Verify platform admin, operations, support, compliance, and employer admin roles if applicable.
- Disable write access in the old system unless rollback requires a controlled exception.
- Confirm support team can search customers and handle payment/KYC/loan issues.

## Customer Communication

Before cutover:

- Notify customers of maintenance window if there will be downtime or degraded service.
- Explain repayment behavior during the window.
- Provide support contact details.

At cutover:

- Confirm service is available.
- Publish any required customer-facing status notice.

After cutover:

- Communicate successful completion if customers were notified of maintenance.
- Monitor support contact volume and repayment failures.

## Post-Cutover Validation

Within the first hours:

- Login smoke test for customer and admin roles.
- Create or view customer profile.
- View KYC and consent status.
- Submit or inspect loan application workflow according to approved scope.
- Verify active loan balances and schedules for sample accounts.
- Process approved test payment/status lookup where safe.
- Confirm provider callbacks are received.
- Confirm ledger postings balance.
- Confirm audit logs are created.
- Confirm support case workflow.
- Confirm required reports generate.

Within the first business day:

- Reconcile all payments received during cutover.
- Compare loan book summary against final migration manifest.
- Review error logs, failed jobs, queue backlog, and alerts.
- Review support issues and customer complaints.
- Decide whether hypercare can continue or rollback must be considered.

## Hypercare

Run a hypercare period after cutover with:

- Engineering on-call.
- Operations lead.
- Finance reconciliation owner.
- Compliance owner.
- Support lead.
- Provider contact path for MTN/Airtel/KYC/CRB.
- Hourly checks during the first day, then daily checks until stable.
