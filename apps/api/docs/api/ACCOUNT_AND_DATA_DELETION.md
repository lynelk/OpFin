# Account and optional-data deletion

Updated: 2 October 2026
Status: release-candidate contract; production deployment is assessed separately.

Customers may delete their account or selected optional data while keeping it. Web and Flutter use the same authenticated API. The legacy public web form verifies the supplied phone and current PIN/password before using the same account-closure service.

Full closure requires re-authentication and DELETE confirmation. OpFin checks current obligations across its represented financial domains, including canonical financing arrangements, Essentials collection exceptions, peer lending, savings, claims, investment/capital mandates and payroll reservation release. Any blocker causes immediate rejection with structured references, status, relevant amount/date and only actually recorded provider contacts. No misleading pending request is created; servicing access remains available. Resolve the obligations, then submit a fresh request.

The authenticated web `/account/delete` page supports readiness and selective-data deletion without reinstalling the app. Flutter exposes the same choices under account/privacy. Only explicit `deletion_status=completed` permits client session erasure. `data_deleted` confirms selected optional data deletion without closing the account.

Financial planning, optional links/profile preferences and requester-owned optional discovery locations can be removed. Regulated financial, KYC/AML, credit-reporting, settlement, accounting, security and audit evidence requiring lawful retention is preserved. Another user's data and retained asset/claim locations are never optional-data deletion targets.

A missing provider phone/email/address is stated as unrecorded; retain the obligation reference. A canonical contract total is labelled as such rather than presented as a current outstanding balance.


## Payroll, partner referral and account-deletion release contracts (2 October 2026)

These are source/release-candidate contracts, not evidence of financial-provider activation or production deployment.

| Method | Endpoint | Contract |
| --- | --- | --- |
| GET | `/api/account/deletion-readiness` | Current authenticated structured blockers, optional categories and retention disclosure |
| DELETE | `/api/account` | PIN/password re-authentication and `confirmation=DELETE`; `completed` is the only successful closure result; any obligation returns HTTP 409 `blocked_obligations` immediately |
| DELETE | `/api/account/data` | Re-authentication, `confirmation=DELETE_DATA` and `data_categories`; explicit `data_deleted` keeps the account active |
| GET | `/api/financing-applications` | Current customer's financing applications |
| GET/POST | `/api/payroll-deduction/cases` | Read/start an eligible salary-finance case inside an active authorised Financial Space |
| GET | `/api/payroll-deduction/cases/{case}` | Customer-owned case and attempt-specific reconciliation states |
| GET | `/api/payroll-deduction/provider-capability` | Honest inactive/live adapter capability, not fabricated PDMS certification |
| POST | `/api/payroll-deduction/cases/{case}/undertaking` | Atomically record the case/reference/amount-bound payroll undertaking and request reservation; `authorised=true`, positive `requested_deduction_minor`, optional agreement reference |
| POST | `/api/payroll-deduction/cases/{case}/reservation` | Compatibility request requiring a current case-bound undertaking consent; generic credit consent is insufficient |
| POST | `/api/payroll-deduction/cases/{case}/cancel` | Local cancellation only before uncertain/external reservation exposure; otherwise `cancellation_pending` until evidenced release |
| GET | `/api/operations/payroll-deduction/cases` | Authorised operations queue |
| GET | `/api/operations/payroll-deduction/cases/{case}` | Operations case, immutable transition evidence and per-attempt results |
| POST | `/api/operations/payroll-deduction/cases/{case}/affordability` | Record affordability evidence and buy-off exception, not an invented external check |
| POST | `/api/operations/payroll-deduction/cases/{case}/reservation` | Positive confirmation requires reservation/agreement references and a current undertaking |
| POST | `/api/operations/payroll-deduction/cases/{case}/key-facts` | Current reservation and mandate required; retain submitted facts |
| POST | `/api/operations/payroll-deduction/cases/{case}/vote-decision` | Positive approval checks locked reservation expiry and current undertaking |
| POST | `/api/operations/payroll-deduction/cases/{case}/payroll-submission` | Government Code 482; increment submission attempt, preserve prior attempts |
| POST | `/api/operations/payroll-deduction/cases/{case}/payroll-result` | Period must match submission; each attempt retains original outcome, amounts and provider evidence |
| POST | `/api/operations/payroll-deduction/cases/{case}/amend` | Return to reservation-pending, never manufacture renewed external reservation |
| POST | `/api/operations/payroll-deduction/cases/{case}/reconcile` | Derive expected amount from recorded result; operator cannot force a match by supplying an expected amount |
| POST | `/api/operations/payroll-deduction/cases/{case}/cancellation-release` | Require explicit released status and recorded release reference before closure |
| POST | `/api/partner/financial-intents/{customer}` | Governed active partner source binding, mandatory stable idempotency key, no financial commitment on customer's behalf |
| GET | `/api/partner-financial-intents` | Current customer's referrals |
| POST | `/api/partner-financial-intents/{request}/confirm` | Active/nondeleted Space authority; once-only canonical intent and central audit; changed Space/principles replay rejected |
| POST | `/api/partner-financial-intents/{request}/decline` | Customer-controlled, audited decline without creating a financial intent |

All payroll writes require `Idempotency-Key`; a supplied `X-Correlation-ID` must be a UUID. Keys are bound to command, canonical payload and actor. The Flutter application stores uncertain command identity in its protected session store and reuses it after interrupted responses; changing an uncertain instruction is rejected. A plain HTTP 200 is not confirmation of account closure or financial finality.

The account blocker record contains `code`, `label`, `reference`, `status`, `amount_minor`, `currency`, `due_date` and `provider` with recorded name/phone/email/address and `direct_contact_available`. Missing contacts remain null and must be described as unrecorded. `amount_basis=contract_total_not_current_balance` explicitly identifies a canonical arrangement's contract total rather than an asserted live debt balance. Structured blocker details must not enter redirect URLs.

Closure checks include canonical financing arrangements, compatibility loans, Essentials advances and unresolved durable collection/reversal instructions, recorded personal obligations, peer commitments, savings, protection/claims, investments, capital mandates, payroll reservations and pending financial actions/movement represented in the schema. Rejection creates no misleading pending support/deletion request and does not revoke servicing access. Once resolved, the customer submits a fresh request.

Optional deletion categories are `location_context`, `financial_planning`, `linked_accounts`, `household_and_microbusiness`, and `profile_preferences`. Location deletion is restricted to the requester's optional personal discovery context; it cannot erase another member's data or regulated asset/claim evidence. Required accounting, KYC/AML, credit reporting, settlement, security, consent and audit evidence are retained.

Partner source attribution is the separately reviewed `financial_intent_source_platform` of the partner account. Existing accounts are not backfilled with invented provenance. The existing independent partner approval workflow may configure the permitted source. `ALL_SUITABLE` does not waive valid Sharia approval for Islamic products.

### Final review corrections

Payroll currency is copied from the authoritative financing product; client-supplied mismatches are rejected. Positive reservation confirmation requires the operations caller to supply the agreement reference independently, not inherit a customer-declared reference. Nonterminal premium processing and reversal exceptions block account deletion, including when the policy itself is cancelled. Fully reversed legacy loans and declined claims are terminal for deletion checks, without deleting their required history. The forward payroll migration installs immutable-event protection on databases that ran an earlier schema.
