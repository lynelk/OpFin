# Rollback Strategy

Date: 2026-05-22

Rollback restores service to the current live system if the new OpFin build cannot safely serve customers or operations after cutover.

## Rollback Principles

- Protect customer funds, loan balances, repayment records, consent, KYC evidence, and audit trail first.
- Do not discard new-system activity without reconciliation.
- Do not route provider callbacks to two writable systems at once.
- Communicate clearly to customers and staff.
- Keep accountable owners named before cutover starts.

## Conditions for Rollback

Rollback should be considered if any critical condition occurs:

- Customers cannot log in or access required loan/payment information.
- Admin/support users cannot perform critical support or payment operations.
- MTN/Airtel callbacks are failing or routed incorrectly.
- Payments are duplicated, missing, or cannot be reconciled.
- Loan balances or repayment schedules are materially incorrect.
- Ledger entries are unbalanced or missing for money-moving actions.
- KYC, consent, or credit decision gates are bypassed.
- Compliance reports cannot be produced for required obligations.
- Severe security, authorization, or data exposure issue is found.
- Error rate, queue backlog, or infrastructure failure exceeds agreed thresholds.

## Rollback Owners

| Area | Owner role |
| --- | --- |
| Overall decision | Business owner and engineering lead |
| Customer communication | Support lead and business owner |
| Provider routing | Engineering lead and provider operations contact |
| Data reconciliation | Finance lead and data migration lead |
| Compliance impact | Compliance lead |
| Admin operations | Operations lead |
| Incident record | Engineering lead |

## Rollback Steps

1. Declare rollback incident and timestamp.
2. Stop new customer/admin writes in the new system where possible.
3. Preserve new-system database snapshot and logs.
4. Export all new-system activity since cutover:
   - customer profile changes
   - KYC changes
   - consent changes
   - applications
   - loan state changes
   - mobile money transactions
   - ledger transactions
   - support cases
   - audit logs
5. Identify money-moving events that occurred after cutover.
6. Reconcile post-cutover payment/provider events before old system resumes writes.
7. Route customer/API traffic back to the old system.
8. Route MTN/Airtel callbacks back to the old system or to a temporary controlled receiver approved by engineering.
9. Restore old-system admin write access.
10. Apply approved post-cutover deltas to the old system if required.
11. Validate old-system customer login, loan balances, payment status, and admin workflows.
12. Communicate rollback status to staff and customers.
13. Keep the new system read-only for investigation.

## Data Implications

Rollback is simple only if the new system did not accept writes. If the new system accepted writes, each write must be classified:

| New-system activity | Rollback handling |
| --- | --- |
| Customer login/session | No data migration required unless account recovery changed credentials. |
| Customer profile update | Apply to old system or ask customer/support to repeat after rollback. |
| KYC submission/review | Preserve evidence; apply to old system only after compliance review. |
| Consent grant/revoke | Apply to old system before any future credit processing. |
| Loan application | Import into old system or mark cancelled and notify customer. |
| Loan offer acceptance | Requires finance/operations decision before rollback because it may create loan and disbursement obligations. |
| Mobile money disbursement | Must reconcile provider status before old system resumes disbursement for that loan. |
| Repayment collection | Must reconcile provider status and update old system balance exactly once. |
| Ledger posting | Preserve as audit evidence; map to old accounting records or reversal/correction workflow. |
| Support case | Export and transfer to old support workflow. |
| Audit log | Preserve permanently for incident traceability. |

## Communication Plan

### Internal

- Notify operations, support, finance, compliance, engineering, and business owners.
- State rollback reason, customer impact, payment handling instructions, and support script.
- Provide clear instruction on which system is writable.

### Customer

Use customer communication only if customers are affected or were notified of cutover.

Message should include:

- Service restoration status.
- Whether repayment or application actions need to be retried.
- Support contact.
- Assurance that balances and payments are being reconciled.

### Providers

- Notify provider contacts if callback routing or settlement handling changed.
- Confirm active callback URL.
- Request provider transaction status export for the incident window if needed.

## Post-Rollback Review

After rollback:

1. Complete incident timeline.
2. Reconcile all post-cutover transactions.
3. Identify root cause.
4. Update migration/cutover scripts and runbooks.
5. Repeat staging rehearsal.
6. Do not attempt another cutover until the failed condition has a verified fix.
