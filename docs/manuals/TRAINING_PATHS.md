# OpFin training and user-guide learning paths

Review date: 18 September 2026. These paths organise existing source material; they are not a claim that every screen has been accepted in production.

## Customer and support learning path

| Module | Practical outcome | Current source | Acceptance focus |
| --- | --- | --- | --- |
| Account and secure access | Explain phone verification, names and the six-digit PIN journey | [Training foundation](../TRAINING_AND_USER_GUIDE_FOUNDATION.md) | No staff request for a PIN or OTP; recovery follows the verified flow |
| Identity and assistance | Explain required identity evidence and accessible assistance | [Launch journey](../LAUNCH_CUSTOMER_JOURNEY.md) | Unavailable or failed checks do not become verified identities |
| Profile and available credit | Explain the score, available limit, amount due and next action | [Training foundation](../TRAINING_AND_USER_GUIDE_FOUNDATION.md) | No promise of approval or invented scoring data |
| Application and offer | Review the exact offer disclosures before acceptance | [Current endpoints](../../apps/api/docs/api/current-endpoints.md) | Customer understands amount received, costs and repayment obligations |
| Wallets and payments | Distinguish request, pending state, completion and receipt | [Client contract](../../apps/api/docs/api/frontend-backend-contract.md) | Interrupted or repeated actions do not justify duplicate payment requests |
| Support and complaints | Capture a case, explain the next step and escalate appropriately | [Digital-lending controls](../UMRA_DIGITAL_LENDING_CONTROLS.md) | Use configured deadlines and accurate references; no fabricated resolution promise |

## Operations learning path

Start with [the operational runbook](../../apps/api/docs/operations/operational-runbook.md), then [readiness checks](../../apps/api/docs/operations/production-readiness-checklist.md) and [customer UAT](../../apps/api/docs/uat/customer-uat-scenarios.md). Cover permissions, maker/checker boundaries where applicable, reconciliation, provider uncertainty, incident escalation and release evidence.

## Developer and integrator learning path

Read [developer setup](../DEVELOPER_START_HERE.md), [the glossary](../GLOSSARY.md), [the integrator guide](../../apps/api/docs/api/INTEGRATOR_GUIDE.md) and [contribution rules](../../CONTRIBUTING.md). Demonstrate a safe local health check, find an endpoint, identify its controller/tests, explain one failure path, and update the relevant guide alongside a code change.

## Producing finished manuals

Create each lesson from [the task-guide template](TASK_GUIDE_TEMPLATE.md). Retain source links and commit/build metadata rather than duplicating business rules without provenance. Record tested screen labels and accessible recovery instructions. Mark missing acceptance evidence `UNVERIFIED`; do not turn an intended flow into a verified claim.
