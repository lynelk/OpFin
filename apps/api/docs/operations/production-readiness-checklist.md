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


## UMRA credit and consumer-protection activation

- [ ] Licensed entity legal name configured and verified.
- [ ] Registered/trading name confirmed.
- [ ] UMRA licence/reference configured and independently verified.
- [ ] Physical business address configured and displayed in digital disclosures.
- [ ] Official complaints email, telephone and URL configured/tested.
- [ ] Complaint 30-day clock appears in customer/admin records.
- [ ] Applicable authorised credit-reference reporting provider/endpoint/schema certified.
- [ ] Positive and negative test reports pass provider validation using authorised fixtures.
- [ ] Missing reporting consent blocks external bureau transmission.
- [ ] Incomplete identity/data quality blocks external bureau transmission.
- [ ] NPL scan identifies an overdue controlled loan correctly.
- [ ] Default-interest ceiling/recovery-cap test produces no breach under normal policy.
- [ ] Per-loan enforcement control is restricted to authorised admin/operations roles and audited.
- [ ] Successful disbursement and repayment create exactly one e-receipt after finality.
- [ ] SMS/other instant acknowledgement path tested.
- [ ] Guarantor flow limits contacts to two and independent confirmation/rejection works.
- [ ] Contact-list permission is absent.
- [ ] Direct interest-rate editing is blocked.
- [ ] Maker-checker term change works.
- [ ] Interest-rate change cannot apply without recorded prior UMRA approval evidence.
- [ ] UMRA books/records, credit exchange, NPL, receipts, complaints and term/guarantor reports generate and validate.
- [ ] Compliance officer reviews report drill-down and approval workflow.
