# OpFin Operational Manual

Version: 21 September 2026
Audience: OpFin operations, support, finance, compliance and authorised institutional administrators

## Operating model
OpFin separates Person identity, Financial Spaces, membership/roles, capabilities, entitlements and product eligibility. CPay is the execution/reconciliation boundary where configured for money movement. Revenue events attribute commercial economics and do not replace the financial ledger.

## Daily controls
Review health/readiness, worker and scheduler status, provider callbacks, reconciliation queues, failed financial actions, support cases, KYC/consent exceptions and security alerts. Never resolve a money-state exception by editing customer-facing status without provider/ledger evidence.

## Financial Space administration
A person can belong to many Spaces. Validate role before any administrative action. Personal Space information must not be disclosed to employers, groups or other organisations because of membership alone.

## Institutional onboarding
Business/SACCO/Fund/Partner progression is profile → KYB → regulatory evidence → products → integration → certification. Do not activate regulated product distribution merely because a technical capability is enabled.

## Employer operations
Employer is a capability on a Business Space. Employment relationships may support benefits or financial-wellness services, but employee Personal Space data remains private unless a specific lawful consent/process authorises data sharing.

## Partner Catalogue
Maintain partner status, product lifecycle, territory, eligibility, pricing/disclosures and integration configuration. Customer need/eligibility/suitability precedes commercial economics.

## Revenue operations
Revenue event types include subscription, commission, revenue share, transaction, platform fee, API fee and servicing. Reconcile each applicable event to CPay/provider references and settlement evidence. Investigate duplicates through source idempotency rather than deleting evidence.

## Incident handling
For partner outage, preserve pending state, avoid duplicate execution, retry only under idempotent rules, reconcile provider truth, communicate customer-safe status and retain audit evidence. Escalate suspected financial-integrity or privacy incidents immediately.

## Release operations
A release requires migrations, API/client tests, cross-Space isolation, financial integrity/idempotency, partner failure/recovery, accessibility/mobile-completeness evidence, revenue reconciliation and documentation alignment. Production success is not a substitute for CI evidence.


## Inclusive-finance programme operations
Use **Inclusion & programmes** to configure and review programme delivery. A programme may be linked to a sponsor Financial Space and partner, have start/end dates and apply explicit participation rules. Inclusion rules govern programme participation only and must never be copied into the credit-scoring model.

Programme-measurement attributes require customer consent. Withdrawal clears the stored voluntary inclusion attributes. Never fill a customer's demographic or disability field by inference.

Customers may voluntarily leave a programme. Exit is idempotent and records `exited_at` plus a `programme_exited` impact event. Historical reporting retains the participant but excludes credit/capability outcomes after exit. The current single-period model deliberately blocks silent automatic re-enrolment after exit; any future re-entry mechanism must preserve the prior participation window.

## Alternative-data operations
Provider signals begin as non-risk eligible. Only independently verifiable provider signals with an approved credit-assessment/affordability purpose and active credit-processing consent may be marked eligible for an approved scoring policy. Protected demographic/accessibility attributes remain prohibited as risk inputs. Risk eligibility alone does not change a score, limit, price or approval.

## Alternative collateral and support
Support instruments are evidence records. Externally evidenced collateral such as warehouse receipts, receivables and asset evidence require a provider/issuer and external reference before verification. Verification alone does not create an approval; the applicable product policy must recognise the instrument.

## Impact reporting
Use aggregate programme reporting for enrolments, applications, decision outcomes, NPL state and capability evidence. Only consented measurement profiles enter cohort reporting. If any group within an inclusion dimension is smaller than five, that whole dimension is suppressed to reduce differencing risk. Credit outcomes are scoped to the participation window. Participant capability events are not to be described as programme-caused unless a directly attributed intervention supports that conclusion.

## Accessibility operations
Support staff should know how to guide customers to large text, simple wording, reduced motion and high contrast. Screen-reader and other assistive-technology behaviour must be physically tested on supported devices before certification or public claims.
