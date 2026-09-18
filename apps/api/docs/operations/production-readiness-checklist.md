# Production readiness checklist

Updated: 18 September 2026

This checklist applies to the launch borrower journey. Source-code completion is necessary but not sufficient for production activation.

## Release identity

- [ ] Exact candidate commit recorded.
- [ ] `release-gate` passes on that exact commit.
- [ ] `security-gate` passes on that exact commit.
- [ ] Deployment-contract workflow passes.
- [ ] Android signed candidate package ID/version/signing digest recorded.
- [ ] Deployed API/web/worker/scheduler commit matches the approved source.

## Customer journey

- [ ] Phone → OTP → names → six-digit PIN works on physical Android device.
- [ ] OTP auto-fill works where supported without READ_SMS; manual fallback works.
- [ ] Registration returns authenticated session and opens Home without redundant login.
- [ ] Home shows one clear next action, amount due or available limit as appropriate.
- [ ] Second phone is demonstrably optional.
- [ ] Loan Application shows available limit and amount due.
- [ ] Only API-eligible repayment periods appear.
- [ ] Formal offer disclosure matches backend snapshot exactly.
- [ ] Verified payout wallet selection works.
- [ ] Repayment uses verified wallet + idempotency key and pending wording until provider success.

## Identity/KYC

- [ ] `KYC_FILESYSTEM_DISK` points to private persistent/object storage, not ephemeral runtime storage.
- [ ] Storage read/write/delete/access-control test completed.
- [ ] Identity provider URL/token configured in production secrets.
- [ ] NIN, liveness, face match and NIN/phone-link outcomes tested with authorised fixtures.
- [ ] Provider outage/inconclusive result stays pending/review, never auto-verifies.
- [ ] KYC evidence paths/raw payloads absent from customer responses/logs.
- [ ] Assisted/PWD identity request reaches Support and does not require PIN/OTP disclosure.

## Credit decisioning

- [ ] CRB provider configured/certified.
- [ ] MNO scoring adapter configured where used.
- [ ] Approved third-party scoring adapter configured where used.
- [ ] Each source result contains attributable source/reference/timestamps and expiry.
- [ ] Missing source data remains unavailable/error, not synthetic.
- [ ] Composite score weights/coverage reviewed and approved.
- [ ] Limit bands reviewed and approved for Uganda launch.
- [ ] Verified affordability provider supplies `verified_monthly_income_minor` (and obligation where available) before automatic approval.
- [ ] Requests without verified affordability refer to controlled review.
- [ ] Configured maximum debt-service ratio approved and tested.
- [ ] Amount above server available limit is rejected.

## CPay / money movement

- [ ] Production CPay credentials/service scope verified.
- [ ] Disbursement to each intended verified wallet/network tested using authorised test accounts.
- [ ] Collection request, success, failure, timeout and reversal exercised.
- [ ] Pending provider state does not update economic balance.
- [ ] Ledger remains balanced across disbursement, repayment and reversal tests.
- [ ] Reconciliation exceptions surface to operations.
- [ ] Duplicate repayment idempotency test passes.

## WhatsApp

- [ ] Meta webhook verify token configured.
- [ ] Meta app secret configured and invalid signatures rejected in production.
- [ ] WhatsApp access token/phone number ID configured.
- [ ] START/session verification tested.
- [ ] Unregistered user is sent to secure registration, not asked for PIN in chat.
- [ ] NIN → ID front → ID back → selfie-with-ID media journey tested.
- [ ] Media is copied to private KYC storage and provider media is not treated as permanent storage.
- [ ] LIMIT/PROFILE state matches the app for the same customer.
- [ ] BORROW/REPAY use secure hand-off and cannot complete high-impact action from free text alone.

## USSD

- [ ] Aggregator callback authentication configured (`USSD_SHARED_SECRET` or approved provider-native equivalent).
- [ ] Short code and aggregator contract provisioned externally.
- [ ] Menu fits expected session/character limits.
- [ ] My limit/My loan state matches the app.
- [ ] KYC image step hands off safely rather than pretending USSD can capture media.
- [ ] Session retry/resumption behaviour tested with aggregator.

## Accessibility and comprehension

- [ ] TalkBack test completed on physical Android candidate.
- [ ] VoiceOver test completed where iOS release is in scope.
- [ ] OS large text and OpFin larger-text mode tested on critical screens.
- [ ] Reduced-motion preference verified.
- [ ] Primary actions have meaningful semantics and practical touch targets.
- [ ] Status does not rely on colour alone.
- [ ] Low-literacy moderated UAT confirms users can identify limit/amount due and next action.
- [ ] PWD/accessibility representative or adviser reviews assisted-KYC flow.
- [ ] Trusted-helper guidance explicitly protects PIN/OTP.

## Privacy and stores

- [ ] Privacy-policy URL live and accurate for KYC/scoring/wallet processing.
- [ ] Account-deletion URL live.
- [ ] Google Play financial-features/Data Safety declarations match the actual candidate.
- [ ] Camera permission is justified; no broad SMS/media/storage permission added.
- [ ] Actual legal lender/provider/licence evidence verified for launch arrangement.
- [ ] Actual fee-inclusive maximum APR and representative example verified.
- [ ] Store screenshots captured from the exact signed candidate with authorised test data.

## Operations

- [ ] Database migrations backed up and rehearsed.
- [ ] Restore test completed.
- [ ] Worker/scheduler heartbeats healthy.
- [ ] Support can see KYC/accessibility cases without unnecessary identity evidence exposure.
- [ ] Admin roles/least privilege reviewed.
- [ ] Provider outage playbooks and customer-safe wording reviewed.
- [ ] Incident contacts, PDPO/regulatory escalation and security reporting channel verified.
- [ ] Independent security test scheduled/completed according to release policy.

Production sign-off requires Product, Compliance, Finance, Operations, Support, Engineering and accessibility/PWD review for the exact candidate.


## UMRA credit-information exchange

- [ ] Exact certified credit-reference reporting endpoint/schema is configured in `CRB_REPORTING_URL`.
- [ ] Reporting token/credentials are environment-scoped and least privilege.
- [ ] Positive disbursement/repayment/clearance and negative NPL events stage correct complete payloads.
- [ ] Outbound submission is blocked without active `credit_reporting` consent tied to an accepted offer.
- [ ] Missing NIN/name/phone/account fields create a visible failed/incomplete record rather than a partial submission.
- [ ] Idempotent retry and provider-reference capture have been exercised.
- [ ] Admin UMRA Control Desk shows queue status and failed records.
- [ ] Portfolio snapshot command is running on schedule.

## UMRA NPL/default-interest controls

- [ ] Compliance/legal team confirms the implemented section-14 calculation against any current UMRA interpretation.
- [ ] `OPFIN_UMRA_NPL_CAP_MODE=enforce` is confirmed for launch or an approved written exception records why track-only is used.
- [ ] Principal at NPL is frozen on first classification and does not fall with later principal payments.
- [ ] Default-penalty ceiling, interest-recovery ceiling and total recovery cap are visible in Admin.
- [ ] Collection above remaining configured cap is rejected in enforce mode.
- [ ] Hourly NPL evaluation and negative CRB staging is healthy.

## UMRA receipts and complaints

- [ ] Provider-confirmed disbursement generates exactly one in-app e-receipt.
- [ ] Provider-confirmed repayment generates exactly one in-app e-receipt.
- [ ] Pending provider requests never generate successful receipts.
- [ ] Receipt SMS is not recorded delivered until delivery evidence exists.
- [ ] Complaint channel/30-day SLA is disclosed in the loan offer.
- [ ] Complaint cases receive a regulatory category and 30-day SLA due date.
- [ ] Resolved/closed complaints require a customer-facing resolution summary.
- [ ] Overdue open complaints appear in UMRA books/reports.

## Guarantors and term changes

- [ ] Product terms allow only 0, 1 or 2 required guarantors.
- [ ] No contact/SMS permission is introduced to source guarantors.
- [ ] Guarantor receives an explicit loan-specific consent message before verification.
- [ ] Application decisioning pauses until the required electronically verified guarantors exist.
- [ ] Accepted loan offer/disclosure remains immutable.
- [ ] Proposed term changes are versioned separately and require exact customer consent.
- [ ] Interest-rate changes require prior UMRA approval reference + SHA-256 evidence before customer acceptance.
- [ ] Generic automatic mutation of accepted live-loan economics remains disabled unless a separately reviewed versioned amendment executor is introduced.
- [ ] Existing product catalogue interest-rate edits require prior UMRA approval evidence.

## UMRA books, registers and inspection

- [ ] Generate each UMRA report profile for an authorised test period.
- [ ] Validate JSON and CSV exports and evidence hash headers.
- [ ] Maker-checker approval is exercised by two different authorised officers.
- [ ] Loan book, CRB exchange, NPL, receipts, complaints, guarantors and variation registers reconcile to source tables.
- [ ] Exact UMRA prescribed return template/submission channel is mapped if UMRA supplies a format beyond the internal evidence pack.
