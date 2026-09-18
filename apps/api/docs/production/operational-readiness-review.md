# Operational readiness review

Updated: 18 September 2026

## Summary

OpFin now has production-shaped borrower, payment, ledger, reconciliation, support, compliance and regulatory-control workflows. The remaining readiness questions are mostly external activation and operational evidence, not missing core source modules.

## Current controls

| Area | Current position |
| --- | --- |
| Health/readiness | API health endpoints and release/deployment gates exist |
| Queue/scheduler | Queue/SMS jobs and scheduled financial/regulatory controls exist |
| Payment operations | CPay boundary, provider finality, idempotency, reconciliation and reversals |
| Credit operations | Score/profile, affordability, decision, offer and manual-review paths |
| Complaints | Case management, 30-day regulatory due date, SLA alerts |
| Credit information | Positive/negative outbound register, consent/data-quality gates and retry history |
| NPL | Overdue/NPL evaluation, cap evidence and enforcement state |
| Receipts | Provider-finality-backed receipt register and acknowledgement |
| Governance | Maker-checker term/rate workflow and regulator report approval |
| Reporting | UMRA/books/records evidence packs with hashes/validation/detail |
| Accessibility | Software support; real-device sign-off remains external evidence |

## Required operational evidence before general availability

- queue worker/scheduler health in production;
- backup and restore drill;
- private KYC storage access/retention controls;
- provider callback/credential certification;
- credit-reporting schema/provider certification;
- reconciliation exception ownership;
- incident and provider-outage rehearsal;
- official complaint contacts and staffing;
- regulatory/licence evidence;
- real-device accessibility/PWD UAT;
- exact deployed commit verification.

## Runbooks

Use:

- `../operations/operational-runbook.md`
- `production-readiness-checklist.md`
- `monitoring-and-alerting-plan.md`
- `backup-and-restore-plan.md`
- `incident-response-runbook.md`
- `provider-callback-runbook.md`
- `../../../docs/UMRA_DIGITAL_LENDING_CONTROLS.md`
