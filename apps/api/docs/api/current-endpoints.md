# Current API endpoints

Status: Controlled external developer reference  
Updated: 24 September 2026  
Language: English (United Kingdom)

Updated against the registered canonical platform routes on **24 September 2026**, including the Location Context capability. Routes remain subject to the middleware and role gates in source.

All JSON API responses use the standard envelope:

```json
{
  "success": true,
  "message": "Human-readable result",
  "data": {}
}
```

Validation/business failures return `success: false` with safe error details.

## 1. Health

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/health` | Combined application health |
| GET | `/api/health/live` | Liveness |
| GET | `/api/health/ready` | Readiness, including runtime dependencies/heartbeats |

## 2. Account authentication

Public, throttled:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/generate-otp` | Send a six-digit OTP; accepts optional Android app signature for SMS Retriever |
| POST | `/api/verify-otp` | Verify OTP and return short-lived phone-verification token |
| POST | `/api/register` | Create account after verified phone; preferred payload is names + six-digit PIN |
| POST | `/api/login` | Phone + PIN; legacy password remains migration-compatible |
| POST | `/api/reset-password` | OTP-backed PIN reset; endpoint name retained for compatibility |

Authenticated:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/logout` | Revoke current token |
| DELETE | `/api/account` | Regulated account-deletion workflow; preferred re-authentication field is `pin`, with `password` retained for migrated accounts |
| GET | `/api/profile` | Sanitised profile; NIN is masked |

Preferred registration payload:

```json
{
  "phone": "2567XXXXXXXX",
  "verification_token": "<64-char one-time token>",
  "first_name": "Amina",
  "other_name": "",
  "last_name": "Kato",
  "pin": "482951",
  "pin_confirmation": "482951",
  "terms_accepted": true
}
```

Successful registration returns an access token and authenticated user; clients should go directly to Home rather than forcing another login.

## 3. Phone numbers and wallets

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/phone-numbers` | List profile phone numbers and confirm second phone is optional |
| POST | `/api/phone-numbers/secondary` | Add a second phone after OTP verification |
| GET | `/api/wallets` | List active verified wallets |
| POST | `/api/wallets` | Create/link a wallet to a verified phone |
| PATCH | `/api/wallets/{wallet}/default` | Set disbursement, repayment or both defaults |

Wallet ownership is enforced server-side. Credit limit is profile-level, never per wallet.

## 4. Identity and consent

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/kyc/status` | Sanitised identity status and check results |
| POST | `/api/kyc/cases` | Multipart KYC submission: NIN, ID front, ID back, selfie holding ID |
| GET | `/api/consents` | Current consent records |
| POST | `/api/consents` | Grant explicit versioned consent |
| DELETE | `/api/consents/{consent}` | Revoke consent |

KYC multipart fields:

- `national_id`
- `national_id_front`
- `national_id_back`
- `selfie_with_id`
- optional `capture_channel=app|whatsapp|mobile_web`

Private evidence paths are not customer response fields.

**Identity routing:** when Cito is configured, OpFin uses Cito's provider-neutral capability API as the primary route for NIN validation and phone-ownership/NIN-phone evidence. gnuGrid may satisfy those capabilities behind Cito without OpFin depending on gnuGrid-specific request formats. National ID images and selfie remain separate biometric/document evidence; the current Cito signed capability contract does not accept those binary artefacts, so liveness and face-match stay with the configured evidence-capable provider until an equivalent Cito contract is certified.

If Cito returns an ambiguous technical failure, OpFin leaves the KYC case pending and does **not** silently issue a direct duplicate identity enquiry. Operations must reconcile the original request before explicitly selecting the direct route. If Cito NIN/phone checks pass but no biometric provider is configured, those checks remain valid while liveness/face-match remain `pending_review`; the customer is not falsely marked fully verified.

## 5. Credit profile

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/credit/profile` | Composite score, limit, exposure, due state, setup and next action |
| POST | `/api/credit/profile/refresh` | Refresh eligible source data and recompute profile |
| GET | `/api/credit/options?distribution_channel=play_store` | Eligible active repayment options for the channel |

Typical profile data includes:

- `status`
- `composite_score`
- `band`
- `coverage_percent`
- `credit_limit_minor`
- `current_exposure_minor`
- `available_to_borrow_minor`
- `amount_due_minor`
- `total_outstanding_minor`
- `next_due_date`
- `component_breakdown`
- customer explanations
- setup state and `next_action`

Do not replace unavailable external data with made-up score values.

External credit-data routing is provider-independent: OpFin prefers Cito when configured and otherwise can use the direct provider adapter. A failed or ambiguous Cito request does not silently trigger a second paid enquiry. Operations must reconcile the original request before explicitly selecting the direct route.

Verified positive employer behaviour may provide a small capped score uplift. Missing, unavailable, customer-declined or negative employer-behaviour data is neutral and does not reduce the base Composite Score or its data-coverage calculation.

## 6. Credit applications and offers

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/credit/applications` | Customer's recent applications |
| POST | `/api/credit/applications` | Submit limit-aware request and run automatic decision where eligible |
| GET | `/api/credit/applications/{application}` | Application/decision/offer state |
| GET | `/api/credit/offers` | Customer offers |
| GET | `/api/credit/offers/{offer}` | Full disclosure and disclosure hash |
| POST | `/api/credit/offers/{offer}/accept` | Accept exact disclosures, separately consent to credit-information reporting, and choose verified payout wallet |

Application payload:

```json
{
  "loan_product_id": 1,
  "loan_product_term_id": 3,
  "institution_id": 1,
  "amount_minor": 150000,
  "reason": "School or education",
  "distribution_channel": "play_store"
}
```

The server rejects an amount above `available_to_borrow_minor`.

Offer acceptance:

```json
{
  "accept_disclosures": true,
  "credit_reporting_consent": true,
  "disclosure_hash": "<64-char hash>",
  "wallet_id": 12
}
```

A successful acceptance records a separate versioned `credit_information_reporting` consent and may return `disbursement_pending`; it must not be presented as provider-confirmed money until finality is received.

## 7. Repayment

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/loans/{loan_id}/repay` | Initiate full/partial collection from verified repayment wallet |
| POST | `/api/loans/{loan}/early-settlement-quote` | Freeze a governed early-settlement quote using earned interest, eligible fees/rebates and current default-interest state |
| POST | `/api/early-settlement-quotes/{quote}/settle` | Collect exactly the frozen settlement amount and close the loan only after provider finality |

Required idempotency key is accepted in the `Idempotency-Key` header or body.

```json
{
  "amount_minor": 50000,
  "wallet_id": 12,
  "idempotency_key": "app-repayment:123:unique-value"
}
```

HTTP 202 means the collection request was accepted, not that repayment is economically final.

## 8. Support and accessibility

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/support-cases` | Customer support cases |
| POST | `/api/support-cases` | Create support/assisted-KYC case |
| PATCH | `/api/accessibility-preferences` | Persist language/access preferences |

Accessibility preferences include simple language, large text, screen-reader optimisation, reduced motion and high contrast. High-contrast support is a software preference and does not by itself constitute supported-device accessibility certification.

## 9. WhatsApp and USSD

Public provider callbacks:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/webhooks/whatsapp` | Meta verification challenge |
| POST | `/api/webhooks/whatsapp` | Signed WhatsApp messages/media |
| POST | `/api/ussd` | USSD aggregator callback |

WhatsApp signatures are checked with the configured Meta app secret in production. The WhatsApp KYC sequence supports NIN plus three guided photos. PINs are never collected in chat.

USSD supports status/limit/borrow/repay/loan/profile-help menus but hands image/commitment steps to an authenticated channel. Configure `USSD_SHARED_SECRET` or an approved provider-native equivalent.

## 10. Admin/operations

Existing admin KYC review, CRB ingestion, credit decision approval, offer generation, payment refresh and reconciliation endpoints remain role-gated. Manual approval is a controlled fallback when automatic profile decisioning cannot safely approve.

Demo routes remain disabled unless explicitly enabled in configuration/testing.


## 11. UMRA digital-lending controls

Customer:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/receipts` | Customer transaction/e-receipt history |
| GET | `/api/receipts/{receipt}` | Customer-owned receipt detail |
| GET | `/api/credit/applications/{application}/guarantors` | List guarantor confirmations |
| POST | `/api/credit/applications/{application}/guarantors` | Add one of maximum two manually-entered guarantors |
| POST | `/api/guarantors/confirm` | Independent guarantor confirm/reject response |

Admin/operations:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/admin/umra/credit-reporting` | Credit-information exchange register/status |
| POST | `/api/admin/umra/credit-reporting/submit` | Submit eligible pending outbound reports |
| POST | `/api/admin/umra/loans/{loan}/evaluate-npl` | Evaluate/update UMRA NPL controls |
| POST | `/api/admin/umra/loans/{loan}/default-interest` | Recalculate default interest from governed rate, outstanding principal and elapsed time; optional `as_of_date` only |
| PATCH | `/api/admin/umra/loans/{loan}/npl-enforcement` | Change enforcement state; disabling requires an approved maker-checker financial-control override |
| POST | `/api/admin/financial-controls/loans/{loan}/overrides` | Request a time-bounded financial-control override with evidence |
| POST | `/api/admin/financial-controls/overrides/{override}/approve` | Independent checker approval; self-approval is prohibited |
| GET | `/api/admin/umra/term-changes` | Credit-term governance register |
| POST | `/api/admin/umra/product-terms/{term}/changes` | Submit governed term change |
| POST | `/api/admin/umra/term-changes/{change}/approve` | Maker-checker approval; interest change requires prior UMRA evidence |
| POST | `/api/admin/umra/term-changes/{change}/apply` | Apply approved change to future offers |
| GET | `/api/admin/governance/dashboard` | Governance dashboard and control summary |
| GET | `/api/admin/governance/regulatory-reports` | List generated regulatory reports |
| GET | `/api/admin/governance/regulatory-reports/{report}` | Inspect generated report/books payload and validation evidence |
| POST | `/api/admin/governance/regulatory-reports` | Generate a regulatory report under the governed workflow |
| POST | `/api/admin/governance/regulatory-reports/{report}/approve` | Approve a generated report under the role-gated workflow |
| POST | `/api/admin/governance/integrity-runs` | Run governed integrity checks |
| POST | `/api/admin/governance/integrity-alerts/{alert}/resolve` | Resolve an integrity alert with audit evidence |

Offer acceptance now additionally records explicit electronic consent for complete positive/negative credit-information reporting.


## 16A. Personal financial wellbeing

Authenticated routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/financial-compass` | Personal financial position: recorded available money, safe-to-spend, savings, total known debt, upcoming commitments, cash flow and next best action |
| GET/POST | `/api/financial-accounts` | List or record the person's current cash, mobile-money, bank or other balances |
| PATCH/DELETE | `/api/financial-accounts/{account}` | Update or deactivate a recorded balance source |
| GET/POST | `/api/budgets` | List or create personal budgets |
| PATCH/DELETE | `/api/budgets/{budget}` | Update or deactivate a budget |
| GET | `/api/cash-flow` | Personal cash-flow summary and recorded/imported entries |
| POST | `/api/cash-flow/entries` | Record an income or expense entry |
| PATCH | `/api/cash-flow/entries/{entry}` | Correct a personal cash-flow entry, including category override |
| GET | `/api/financial-calendar` | Upcoming financial events, including server-derived OpFin loan schedules and Personal-Space debt due dates |
| POST | `/api/financial-calendar/events` | Add a scheduled/recurring personal financial event |
| PATCH/DELETE | `/api/financial-calendar/events/{event}` | Update or remove a manual calendar event |

The Financial Compass does not invent unavailable external balances. Total known debt includes server-derived OpFin credit exposure plus open `i_owe` obligations explicitly recorded in the person's canonical Personal Space. A Personal-Space obligation with a due date is included in upcoming commitments and therefore reduces safe-to-spend for the relevant period.

## 16B. Personal savings and protection

Authenticated routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/savings/products` | List approved active partner-held savings products for the requested country |
| GET/POST | `/api/savings/goals` | List or create personal savings goals |
| GET | `/api/savings/goals/{goal}` | Goal detail, confirmed position and movements |
| PATCH | `/api/savings/goals/{goal}/schedule` | Configure the savings schedule subject to current collection controls |
| POST | `/api/savings/goals/{goal}/pause` | Pause a goal |
| POST | `/api/savings/goals/{goal}/resume` | Resume a goal |
| POST | `/api/savings/goals/{goal}/contributions` | Initiate a savings contribution; position changes only after partner confirmation |
| POST | `/api/savings/goals/{goal}/withdrawals` | Request a withdrawal; partner release and provider finality remain separate |
| GET | `/api/protection/products` | List independently approved personal protection products |
| GET | `/api/protection/policies` | List the signed-in person's protection policies |
| GET | `/api/protection/policies/{policy}` | Policy, premium and claims detail |
| POST | `/api/protection/products/{product}/enroll` | Record disclosure-bound enrolment; this does not itself issue cover |
| POST | `/api/protection/policies/{policy}/premiums` | Initiate premium collection |
| POST | `/api/protection/policies/{policy}/claims` | Submit a claim for insurer/underwriter decision |
| POST | `/api/protection/claims/{claim}/dispute` | Request reconsideration of an eligible declined claim |

Protection product audience is explicit: `personal`, `group` or `both`. Personal catalogue endpoints do not return group-only products. Premium collection, partner settlement and insurer policy issuance remain separate states; clients must not call cover active before insurer issuance.

## 16C. Location Context and lightweight Google Maps

Authenticated routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | /api/location/status | Location capability state, Google activation state and explicit background_tracking=false |
| GET | /api/location-contexts?subject_type=...&subject_id=... | List authorised purpose-bound locations for a person, Space, asset, policy, claim or partner service point |
| POST | /api/location-contexts | Create/update one purpose-bound location with source, precision and matching consent purpose |
| DELETE | /api/location-contexts/{context} | Remove an optional location context when authorised |
| POST | /api/location/places/autocomplete | Server-side Google Places autocomplete; server API key is never exposed |
| GET | /api/location/static-map/{context} | Authenticated lightweight Static Map image for an authorised stored context |
| POST | /api/location/route | Optional explicit route calculation between two authorised stored contexts |
| GET | /api/location/nearby-services | One-shot nearby partner-service search; query location is not stored |
| GET | /api/admin/location-insights | Aggregate Financial Space/service-point geography for operations; minimum cohort 5 and no individual customer pins |
| GET | /api/partner/location-network | Programme partner's own recorded service-point network; no customer pins |

Location subjects are user, financial_space, financial_asset, protection_policy, protection_claim and partner_service_point. Purpose is validated against subject type.

Personal service discovery is forced to approximate precision. Device coordinates remain user-reported provenance at the API boundary; only server-resolved Google places or authorised partner/field workflows gain stronger verification state. Every Location Context is credit_decision_eligible=false.

Google-dependent functions fail closed when Google Maps Platform is not configured; manual location remains available.

## 16D. Financial Space treasury, statement import and reconciliation

Available to eligible group/organisation Financial Spaces. Personal Spaces are excluded.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/financial-spaces/{space}/treasury/accounts` | List authorised treasury accounts |
| POST | `/api/financial-spaces/{space}/treasury/accounts` | Create a bank/mobile-money/cash/custodian/broker/investment-wallet account; finance role required |
| GET | `/api/financial-spaces/{space}/treasury/accounts/{account}/transactions` | List internal cashbook transactions |
| POST | `/api/financial-spaces/{space}/treasury/accounts/{account}/transactions` | Record an append-only cashbook transaction; finance role required |
| GET | `/api/financial-spaces/{space}/treasury/accounts/{account}/statement-imports` | Recent external statement imports |
| POST | `/api/financial-spaces/{space}/treasury/accounts/{account}/statement-imports` | Upload/map CSV statement evidence; finance role required |
| GET | `/api/financial-spaces/{space}/statement-imports/{import}` | Inspect imported rows, matches and exceptions |
| POST | `/api/financial-spaces/{space}/statement-imports/{import}/reconcile` | Run confidence-scored auto-reconciliation and return only unresolved to-dos |
| POST | `/api/financial-spaces/{space}/statement-rows/{row}/resolve` | Apply a suggested match, create a missing cashbook entry, or record an accepted external exception |
| POST | `/api/financial-spaces/{space}/statement-imports/{import}/book-transactions/{transaction}/accept` | Accept a documented book-only transaction |
| POST | `/api/financial-spaces/{space}/statement-imports/{import}/balance-variance` | Accept/document a closing-balance variance |
| POST | `/api/financial-spaces/{space}/statement-imports/{import}/confirm` | Confirm only after all review to-dos are cleared |
| POST | `/api/financial-spaces/{space}/statement-rows/{row}/match` | Backwards-compatible direct manual match endpoint |
| GET | `/api/financial-spaces/{space}/statements` | List immutable issued statements |
| POST | `/api/financial-spaces/{space}/treasury/accounts/{account}/statements` | Issue an immutable bank-style account statement; finance role required |
| POST | `/api/financial-spaces/{space}/statements/consolidated` | Issue one professional all-activity statement across all treasury accounts and recorded Financial Space position |
| GET | `/api/financial-spaces/{space}/statements/{statement}` | Read frozen statement snapshot |
| GET | `/api/financial-spaces/{space}/statements/{statement}/html` | Print-ready bank-style HTML |
| GET | `/api/financial-spaces/{space}/statements/{statement}/csv` | CSV statement export |

CSV import maps date, description and either debit/credit or amount+direction. Value date, reference and running balance are optional. Duplicate files are deduplicated by source hash per account.

Reconciliation never silently overwrites the OpFin cashbook. OpFin scores plausible candidates using hard amount/currency/direction/account constraints plus reference, date proximity and description similarity. Only high-confidence candidates auto-match. Lower-confidence candidates become suggested user to-dos.

User resolutions may create a missing book entry or explicitly accept external-only, duplicate, book-only or balance-variance exceptions with reasons. Confirmation remains a separate authorised action after the to-do list is empty.

Issued statements preserve a frozen Financial Space/account presentation snapshot and transaction payload. They cannot be edited/deleted; corrections require a new statement. Consolidated statements include all treasury accounts and a recorded asset/obligation snapshot, with multi-currency totals kept separate.

## 17. Financial Spaces and multi-entity membership

Authenticated routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/financial-spaces` | List Financial Spaces the signed-in person can access |
| POST | `/api/financial-spaces` | Create Household, Savings Group, Investment Club, Business, SACCO, Investment/Fund or Partner Space |
| POST | `/api/financial-spaces/invitations/accept` | Join a Space using a single-use invitation token |
| GET | `/api/financial-spaces/{space}/members` | List members when authorised |
| POST | `/api/financial-spaces/{space}/invitations` | Invite a person with a scoped role |
| PUT | `/api/financial-spaces/{space}/capabilities` | Configure a Space capability when authorised |
| GET | `/api/financial-spaces/{space}/credentials` | List government, regulator, cooperative, tax or other external identifiers attached to the Space |
| POST | `/api/financial-spaces/{space}/credentials` | Declare/update an external Space identifier; verification remains a separate operations action |
| GET | `/api/financial-spaces/{space}/protection/products` | List approved group-capable protection products for an authorised non-Personal Space; catalogue access does not activate group enrolment or premium collection |
| GET | `/api/financial-spaces/{space}/workspace` | Role-aware institutional workspace summary |
| PUT | `/api/financial-spaces/{space}/organisation-onboarding` | Progress Business/SACCO/Fund/Partner onboarding |
| POST | `/api/financial-spaces/{space}/employer/enable` | Enable Employer services on a Business Space |

A person is registered once and can hold different roles in many Spaces. Membership does not grant access to the member's Personal Space. Space `public_id` remains the OpFin identity even when external authority identifiers are later added or verified.

Operations users may verify a declared Space credential through `PATCH /api/admin/financial-space-credentials/{credential}/verification`. Verification records the verifier and optional authority reference/evidence hash; it does not recreate the Space or confer financial-product eligibility.

## 18. Complete financial-life APIs

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/financial-spaces/{space}/financial-life` | Cash, assets, debt, receivables, net position, upcoming commitments and safe-to-spend |
| GET/POST | `/api/financial-spaces/{space}/obligations` | List or record debt, payables and receivables |
| POST | `/api/financial-spaces/{space}/obligations/{obligation}/settlements` | Record settlement against an obligation |
| GET/POST | `/api/financial-spaces/{space}/assets` | List or record assets |

These endpoints are Space-scoped. Cross-Space access is denied unless an active membership/role permits the action.

## 19. Partner Catalogue, plans and subscriptions

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/marketplace/products` | Active eligible-market partner catalogue surface |
| GET | `/api/plans` | Active OpFin plans |
| POST | `/api/financial-spaces/{space}/subscription` | Create/continue a subscription contract and, for paid plans, collect a governed invoice before activating entitlements |

Permission, entitlement and product/regulatory eligibility are separate gates. Commercial economics must not determine financial-health advice.

## 20. Revenue, service economics and financial reconciliation

Operations/admin routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/admin/revenue-events` | Accrue a uniquely identified commercial revenue occurrence; OpFin/partner/tax economics are calculated by governed policy and posted to the canonical ledger |
| POST | `/api/admin/revenue-events/{event}/reconcile` | Settle an accrued revenue receivable against an actual successful money-movement record; free-form settlement assertions are not accepted |
| POST | `/api/admin/service-economics-events` | Record or enrich one idempotent external-service economics event |
| GET | `/api/admin/reports/service-economics` | Provider/customer/partner/Cito/OpFin fee, cost, tax, settlement and margin report |
| GET | `/api/admin/reports/capital-loan-book` | Capital/funding-pool and loan-book performance report |
| GET | `/api/admin/reports/insurance` | Insurance policy, premium, settlement, claims and economics report |
| GET | `/api/admin/reports/savings-investments` | Savings/investment movement, custody/commitment and economics report |
| GET | `/api/admin/reports/employment-positive-behaviour` | Aggregate positive-only employer enrichment report |
| GET | `/api/admin/reports/financial-account-behaviour` | Aggregate linked-account and verified financial-behaviour coverage report |

Paid subscriptions follow contract → invoice → tax → governed payment finality → revenue ledger → entitlement activation. Provider-statement reconciliation remains a separate finality state. Revenue, partner payable and tax payable are canonical ledger postings, not editable reporting labels.

Cito/CPay is the preferred third-party/payment route, not an availability dependency. A production direct money-movement adapter is permitted only when explicitly configured and certified. Provider execution never replaces OpFin product-state, accounting, statement reconciliation or ledger controls.

Service economics distinguishes pass-through principal, premium and capital from revenue. A known zero is stored as `0`; an unknown commercial amount remains `null` and is reported as incomplete rather than guessed.

## 21. Inclusive finance, programme delivery and alternative credit support

Customer routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET/PATCH | /api/inclusive-finance/profile | Read/update optional programme-measurement consent and service preferences |
| GET | /api/inclusive-finance/capability | Contextual financial-capability guidance and current credit position |
| POST | /api/inclusive-finance/capability/events | Record guidance/intervention/outcome evidence |
| GET | /api/inclusive-finance/reputation | Non-score financial-reputation pathway |
| GET/POST | /api/inclusive-finance/signals | Read or submit customer-reported non-risk signals |
| GET | /api/inclusive-finance/programmes | List active programmes |
| POST | /api/inclusive-finance/programmes/{programme}/enrol | Enrol in an open programme |
| DELETE | /api/inclusive-finance/programmes/{programme}/enrol | End the signed-in customer’s programme participation idempotently |
| GET/POST | /api/inclusive-finance/support-instruments | Read or submit alternative credit-support evidence |
| GET | /api/inclusive-finance/fair-treatment | Explain the governed credit-decision boundary |

Admin/operations routes:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | /api/admin/inclusive-finance/programmes | Programme register with enrolment counts |
| GET | /api/admin/inclusive-finance/impact | Aggregate programme outcomes; optional programme_id query filter |
| POST | /api/admin/inclusive-finance/programmes | Create programme configuration |
| PATCH | /api/admin/inclusive-finance/programmes/{programme} | Update programme lifecycle/configuration |
| POST | /api/admin/inclusive-finance/signals | Ingest independently verifiable provider signal |
| PATCH | /api/admin/inclusive-finance/signals/{signal}/verify | Verify signal and, where permitted, mark it eligible for a governed risk model |
| PATCH | /api/admin/inclusive-finance/support-instruments/{instrument}/verify | Verify/reject alternative collateral or guarantee evidence |
| POST | /api/admin/inclusive-finance/fair-treatment/{application}/assess | Persist a fair-treatment decision review |

Programme-measurement attributes are technically separate from credit-decision inputs. Customer-reported signals are never risk eligible. Provider signals require provenance and active credit-processing consent before they may even become eligible for an approved scoring/product policy. Protected demographic/accessibility fields and non-credit-purpose signals cannot be promoted to risk inputs. Eligibility does not automatically alter the Composite Score.

Programme participation rules use an explicit allow-list. Missing voluntary inclusion information produces an incomplete eligibility result; OpFin does not infer it. Impact cohort reporting includes only consented programme-measurement profiles and suppresses an entire inclusion dimension when any bucket is smaller than five, reducing differencing risk. Credit outcomes and participant capability events are scoped to the enrolment-to-exit window, while participant capability events remain separate from direct programme events. Exited participants remain in historical programme totals without extending the outcome window beyond their recorded exit. Alternative collateral verification records evidence; it does not automatically approve a loan or alter pricing. Expired support evidence cannot be verified. Programme codes are normalised case-insensitively and programme effective dates must remain internally valid. Automated fair-treatment assessment is limited to decision reason-code review and is not external-model fairness certification.

## Inclusive Impact & Outcomes

The impact layer extends inclusive-finance programme delivery without changing the credit-decision boundary. All impact, financial-health, livelihood, empowerment and community-finance records created by these routes are non-credit-eligible unless data is independently introduced through the governed alternative-data/scoring path.

### Customer routes

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/inclusive-finance/impact/financial-health` | Return the customer's transparent financial-health check-in history. |
| POST | `/api/inclusive-finance/impact/financial-health` | Record a personal or programme-linked financial-health check-in. Programme-linked observations require active programme measurement consent and enrolment. |
| POST | `/api/inclusive-finance/impact/livelihood` | Record optional livelihood, enterprise and dignified-work observations. Programme-linked observations require active measurement consent and enrolment. |
| POST | `/api/inclusive-finance/impact/empowerment` | Record voluntary programme-only economic-agency observations. Active measurement consent and enrolment are required. |
| GET | `/api/inclusive-finance/impact/community-finance` | Return the customer's community-finance evidence. |
| POST | `/api/inclusive-finance/impact/community-finance` | Record customer-reported savings-group/community-finance evidence. Customer-reported evidence remains non-risk-eligible. |

### Platform administration routes

Requires `platform_admin` or `operations`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/admin/inclusive-finance/indicators` | List the configurable OpFin impact indicator registry. |
| POST | `/api/admin/inclusive-finance/indicators` | Create an impact indicator. Indicators are always created with `credit_decision_eligible=false`. |
| PATCH | `/api/admin/inclusive-finance/indicators/{indicator}` | Update an indicator definition without changing the credit boundary. |
| GET | `/api/admin/inclusive-finance/programmes/{programme}/framework` | Read the programme theory of change and assigned indicators. |
| PUT | `/api/admin/inclusive-finance/programmes/{programme}/theory-of-change` | Create or update inputs, interventions, outputs, outcomes, impact, assumptions, risks and evidence sources. |
| POST | `/api/admin/inclusive-finance/programmes/{programme}/indicators` | Assign a registry indicator and optional target/reporting configuration to a programme. |
| POST | `/api/admin/inclusive-finance/programmes/{programme}/observations` | Record an authorised participant or institutional observation. Participant observations require active programme measurement consent and enrolment. |
| GET | `/api/admin/inclusive-finance/programmes/{programme}/outcomes` | Return privacy-safe indicator summaries and measurement coverage. Participant cohorts below five are suppressed. |
| POST | `/api/admin/inclusive-finance/partner-access` | Grant a dedicated `programme_partner` user aggregate, programme-scoped access for the partner configured on that programme. |

### Programme-partner routes

Requires the dedicated `programme_partner` role and an active `programme_partner_access` grant.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/partner/inclusive-finance/programmes` | List only programmes explicitly granted to the current partner account. |
| GET | `/api/partner/inclusive-finance/programmes/{programme}/impact` | Return existing delivery metrics plus privacy-safe outcome evidence for the granted programme. Individual customer records are not exposed. |

The partner endpoint is reporting-only in this release. Access-level labels (`read_only`, `auditor`, `mel_officer`, `programme_admin`) describe governance scope but do not bypass the aggregate-only API boundary.

## 23. Programme Delivery and Commercial Completion

### Customer programme delivery

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/inclusive-finance/programme-check-ins?channel=app|web|whatsapp|ussd&locale=...` | Return only due programme instruments enabled for the selected channel. Measurement instruments are hidden after programme-measurement consent withdrawal. |
| POST | `/api/inclusive-finance/programme-check-ins/{instrument}/responses` | Submit a typed programme response using the same consent/enrolment boundary across App/Web/channel integrations. |
| POST | `/api/inclusive-finance/programme-follow-ups/{schedule}/open` | Mark a customer-owned follow-up as opened. |
| GET | `/api/inclusive-finance/impact/financial-health/enrichment` | Preview a transparent financial-health snapshot from recorded OpFin financial-life evidence. |
| POST | `/api/inclusive-finance/impact/financial-health/enrichment` | Persist the enriched non-credit financial-health snapshot. |

### Programme partner activation

Public, throttled:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/programme-partner/invitations/accept` | Activate a dedicated programme-partner identity using an invitation token, a separately verified phone verification token, accepted programme-access terms and a non-predictable six-digit PIN. |

The invited phone must match the invitation when one was specified. Existing customer phones/emails cannot be repurposed as partner identities.

### Programme operations

Requires `platform_admin` or `operations`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET/POST | `/api/admin/inclusive-finance/instruments` | List/create metadata-driven programme instruments. |
| PATCH | `/api/admin/inclusive-finance/instruments/{instrument}` | Update lifecycle/channels/locales/schedule configuration. |
| POST | `/api/admin/inclusive-finance/instruments/{instrument}/questions` | Add a typed question and optional indicator mapping. |
| PUT | `/api/admin/inclusive-finance/questions/{question}/translations` | Add/update a reviewed locale translation. OpFin never machine-invents a missing programme translation. |
| POST | `/api/admin/inclusive-finance/follow-ups/generate` | Reconcile scheduled baseline/follow-up/exit measurement tasks. |
| GET | `/api/admin/inclusive-finance/operations` | Due/overdue/completed follow-up and data-quality operations view. |
| POST | `/api/admin/inclusive-finance/instruments/{instrument}/assisted-responses` | Authorised assisted capture while recording the operator separately from the participant. |
| GET | `/api/admin/inclusive-finance/templates` | List reusable programme templates. |
| POST | `/api/admin/inclusive-finance/programmes/{programme}/templates` | Apply a template as editable draft programme configuration. |
| GET | `/api/admin/inclusive-finance/programmes/{programme}/partner-users` | List programme-scoped partner users and pending invitations. |
| POST | `/api/admin/inclusive-finance/partner-invitations` | Create a programme-partner invitation. Delivery is not fabricated; the token is stored encrypted and available only while pending/unexpired. |
| DELETE | `/api/admin/inclusive-finance/programmes/{programme}/partner-users/{user}` | Revoke programme access. |
| GET | `/api/admin/inclusive-finance/programmes/{programme}/exports/{format}` | Download aggregate CSV, XLSX or ZIP report-pack output with cohort suppression preserved. |

### Commercial intelligence

Requires `platform_admin` or `operations`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/admin/commercial/customers/{user}/attribution` | Record/update canonical customer-acquisition attribution. |
| POST | `/api/admin/commercial/costs` | Record governed acquisition/KYC/CRB/payment/support/funding/collection/programme-delivery cost truth. |
| GET | `/api/admin/commercial/dashboard` | Report acquisition, funnel, repeat usage, NPL/overdue outcomes, recorded revenue/cost and contribution. |
| POST | `/api/admin/commercial/graduations/evaluate` | Re-evaluate programme-to-commercial graduation evidence. |
| GET | `/api/admin/commercial/graduations` | Return graduation rate and transparent criteria. Graduation is analytics-only. |

New registrations create a canonical acquisition record. If no source was explicitly supplied the record is marked `other / unattributed_registration`, rather than inventing a marketing source. Analytics capture failure does not block customer onboarding.

### Provider adapter governance

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET/POST | `/api/admin/inclusive-finance/provider-adapters` | List/configure gnuGrid/CRB, MNO, employer, VSLA, Stolets or other governed adapter definitions. |
| POST | `/api/admin/inclusive-finance/provider-adapters/{adapter}/ingestions` | Ingest allow-listed provider evidence with provider reference/provenance. |

An adapter cannot become active unless real credentials/configuration and legal basis are explicitly confirmed. No external secret is stored in the adapter registry. Provider evidence is inserted as verified provenance but remains `risk_eligible=false`; any future underwriting use must still pass the existing alternative-data consent/policy gate.

### Programme-partner exports

A programme-partner with an active explicit programme grant may use:

`GET /api/partner/inclusive-finance/programmes/{programme}/exports/{format}`

The export remains programme-scoped, aggregate-only and privacy-suppressed.
