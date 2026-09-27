# Embedded payroll deduction capability

Status: implementation candidate, provider activation gated  
Reviewed: 27 September 2026  
Language: English (United Kingdom)

## Purpose

This capability embeds salary-linked payroll deduction into OpFin's existing financing lifecycle. It does not create a second loan ledger and it does not treat payroll-provider acknowledgement as repayment finality.

The implemented workflow follows the supplied Uganda payroll-deduction origination process: affordability check, customer undertaking, reservation and agreement reference, Key Facts submission, vote approval, payroll submission, submission-result analysis, amendment where required, and payment reconciliation.

## Activation boundary

The OpFin state machine, customer workflow, operations workflow and reconciliation evidence are implemented independently of the external payroll provider. Payroll event evidence is append-only at both the application and supported database layers. Reconciliation derives the expected amount from the recorded payroll-result obligation; an operator cannot override the expected amount to force a match. The PDMS adapter is fail-closed by default with `PDMS_MODE=manual_evidence`. No live PDMS machine call is made until an approved interface contract, credentials, callback/security rules and production certification are configured.

The Flutter navigation entry is also disabled by default. It is exposed only when the client is built with `--dart-define=OPFIN_PAYROLL_DEDUCTION_ENABLED=true` after the associated product/provider activation decision.

## Customer API

All customer endpoints require `auth:sanctum` and standard API throttling. State-changing payroll operations require an `Idempotency-Key` header. OpFin generates a correlation UUID when the caller does not provide one.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/financing-applications` | List the signed-in customer's integrated financing applications with product and intent context |
| GET | `/api/payroll-deduction/provider-capability` | Read payroll-provider activation state without exposing credentials |
| GET | `/api/payroll-deduction/cases` | List the signed-in customer's payroll-deduction cases |
| POST | `/api/payroll-deduction/cases` | Start payroll processing for a customer-owned financing application |
| GET | `/api/payroll-deduction/cases/{case}` | Read one owned payroll-deduction case |
| POST | `/api/payroll-deduction/cases/{case}/reservation` | Record the customer's undertaking and request a deduction reservation after verified affordability |
| POST | `/api/payroll-deduction/cases/{case}/cancel` | Cancel an eligible pre-final payroll-deduction process |

The customer's raw employment/payroll reference is never persisted by this capability. OpFin stores a SHA-256 fingerprint when such a reference is supplied. Customer responses exclude that fingerprint, provider-state payloads and internal Key Facts evidence.

## Operations API

The operations namespace requires the `platform_admin` or `operations` role. Each state-changing request requires an `Idempotency-Key`.

| Method | Endpoint | Transition/evidence |
| --- | --- | --- |
| GET | `/api/operations/payroll-deduction/cases` | Operations queue, optionally filtered by `status` or `provider` |
| GET | `/api/operations/payroll-deduction/cases/{case}` | Full authorised operations view including events and reconciliation evidence |
| POST | `/api/operations/payroll-deduction/cases/{case}/affordability` | Record affordable, not-affordable or buy-off-required result |
| POST | `/api/operations/payroll-deduction/cases/{case}/reservation` | Confirm or reject the provider reservation |
| POST | `/api/operations/payroll-deduction/cases/{case}/key-facts` | Submit a versioned Key Facts snapshot for vote approval |
| POST | `/api/operations/payroll-deduction/cases/{case}/vote-decision` | Record approved or rejected vote decision |
| POST | `/api/operations/payroll-deduction/cases/{case}/payroll-submission` | Record payroll period, submission file and submission code; default code is `482` |
| POST | `/api/operations/payroll-deduction/cases/{case}/payroll-result` | Record `success`, `rejected`, `off_payroll_lt_3_months` or `off_payroll_ge_3_months` |
| POST | `/api/operations/payroll-deduction/cases/{case}/amend` | Amend a rejected deduction and return it to the controlled reservation/key-facts loop |
| POST | `/api/operations/payroll-deduction/cases/{case}/reconcile` | Match expected and recovered amounts for the payroll period |

## State model

Normal successful path:

`affordability_pending -> affordable -> reservation_pending -> reserved -> vote_approval_pending -> deduction_approved -> payroll_submitted -> reconciliation_pending -> reconciled`

Controlled exception states include:

- `buyoff_quote_required`
- `unaffordable`
- `reservation_failed`
- `vote_rejected`
- `amendment_required`
- `reconciliation_exception`
- `expired`
- `cancelled`

Reservation windows expire rather than remaining indefinitely actionable. A command is provided for controlled expiry processing. Production scheduling must be enabled only through the normal reviewed scheduler configuration.

## Reconciliation and accounting boundary

A successful payroll submission result moves the case only to `reconciliation_pending`. It does not itself close the obligation. Reconciliation records preserve expected amount, recovered amount, variance, payroll period, result category and provider evidence. A zero variance may move the payroll case to `reconciled`; a non-zero variance moves it to `reconciliation_exception`.

This payroll workflow is collection evidence around the financing arrangement. Existing OpFin ledger and repayment services remain authoritative for financial posting, allocation, fee recognition and settlement finality.

## Mobile contract

The shared Flutter client provides a single Android/iOS experience. When the activation flag is enabled, the Borrow surface exposes Payroll deduction. The screen:

- shows provider activation state;
- lists the customer's payroll cases;
- starts the process only for eligible `salary_finance` integrated financing applications;
- records an explicit credit-processing undertaking before requesting reservation;
- prevents a requested deduction above the verified affordable amount;
- explains vote approval, payroll submission, rejection/amendment and reconciliation states without implying that provider submission equals payment finality.

## External dependency still pending

Live PDMS activation requires an official machine-interface specification and production credentials. The source process diagram defines the business flow but does not define API endpoints, authentication, callbacks, rate limits, idempotency behaviour or signing requirements. Those details must not be invented in production code.
