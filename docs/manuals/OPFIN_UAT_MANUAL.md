# OpFin User Acceptance Testing Manual

Version: 21 September 2026

Record tester, build/commit, environment, date, evidence and result for every case.

| ID | Scenario | Procedure | Expected result |
|---|---|---|---|
| UAT-01 | Register person once | Phone → OTP → name → PIN | Account created; Personal Space available; no duplicate persona account |
| UAT-02 | Progressive verification | Use basic money feature, then regulated feature | Basic feature does not demand unnecessary KYC; regulated journey requests required verification |
| UAT-03 | Multi-Space | Create/join Group and Business | Same sign-in sees authorised Spaces with distinct context |
| UAT-04 | Isolation | From Group/Employer context attempt Personal data access | Access denied unless explicitly authorised; no Personal data leakage |
| UAT-05 | Individual mobile completeness | Complete money, budget, debt/receivable, asset and service journeys in App | Normal Individual journey completes without Web |
| UAT-06 | Savings Group mobile completeness | Create group, invite/join, view members and manage group financial context | Normal group journey completes in App |
| UAT-07 | Financial position | Add asset, debt and receivable | Net position reflects recorded values deterministically |
| UAT-08 | Safe to spend | Add available money and upcoming commitments | Guidance reflects commitments and clearly remains guidance |
| UAT-09 | Web enhancement | Open Spaces/Workspace on Web | Same server-authoritative Space context; richer view without changing ownership |
| UAT-10 | Employer | Enable employer on Business | Employer capability enabled without duplicate legal entity or employee Personal visibility |
| UAT-11 | Partner catalogue | Load active products | Only active/territory-appropriate catalogue entries returned |
| UAT-12 | Subscription | Activate plan | Entitlements update separately from permissions/eligibility |
| UAT-13 | Revenue | Record same source event twice | Idempotency prevents duplicate commercial event |
| UAT-14 | CPay reconciliation | Reconcile revenue/payment reference | Provider/reconciliation reference retained and status progresses correctly |
| UAT-15 | Partner failure | Interrupt provider/callback and retry | No duplicate money movement; pending/failure remains visible and recoverable |
| UAT-16 | Offline | Capture supported offline action and reconnect | Queued action syncs once; conflict/replay does not silently overwrite server truth |
| UAT-17 | Low literacy | Complete core App journey using primary labels/icons | Primary action understandable, minimal typing, no finance jargon required |
| UAT-18 | Accessibility | Screen reader/text scaling/touch targets | Core journey remains operable and understandable |
| UAT-19 | Permissions | Member attempts admin action | 403/blocked; authorised official succeeds |
| UAT-20 | Documentation | Compare registered routes/client surfaces/manuals | Current functionality and terminology agree |
| UAT-21 | Programme measurement opt-in | Try to save inclusion fields before consent; then opt in and save | Pre-consent save rejected; post-consent save succeeds; attributes do not appear in credit-decision inputs |
| UAT-22 | Programme consent withdrawal | Save voluntary inclusion details, then withdraw measurement consent | Voluntary measurement attributes are cleared; normal financial journeys remain available |
| UAT-23 | Protected signal boundary | Ingest verified provider demographic/accessibility signal and request risk eligibility | Request blocked; protected field never becomes a risk input |
| UAT-24 | Provider signal consent | Attempt to mark a non-protected provider signal risk eligible before/after credit-processing consent | Blocked without active consent; only eligible after consent and provenance checks |
| UAT-25 | Programme eligibility | Configure a programme with explicit participation rules; test matching, missing and non-matching profiles | Eligible/incomplete/ineligible states are deterministic and do not alter credit score/decision |
| UAT-26 | Programme evidence privacy | Create cohorts below/above minimum reporting size | Groups below five suppressed; consented aggregate cohorts only |
| UAT-27 | Outcome window | Create credit/capability activity before and after enrolment | Credit outcomes report only inside participation window; capability evidence is labelled as participant activity unless directly attributed |
| UAT-28 | Alternative collateral | Submit warehouse receipt without/with issuer reference; verify | Missing external evidence cannot be verified; verified record does not auto-approve credit |
| UAT-29 | Cito/direct provider routing | Configure Cito, run CRB/MNO score, then simulate ambiguous timeout | Cito is used when configured; ambiguous failure remains error/pending and does not silently fire direct provider; explicit route switch is required |
| UAT-30 | Positive employer enrichment | Compare customer with no employer signal, negative signal and verified positive signal | No/missing/negative signal produces no penalty; verified positive signal creates only the configured capped uplift |
| UAT-31 | Service economics reconciliation | Record service event with unknown cost, then enrich/reconcile it | Same idempotent event is enriched; known zero remains 0, unknown remains null, principal/premium is not counted as revenue |
| UAT-32 | Partner reports | Generate capital, insurance, savings/investment and service-economics reports | Totals reconcile to source events; funding-source exceptions and incomplete commercial fields are visible |
| UAT-33 | Certified direct payment adapter | Select unconfigured/uncertified provider then certified test adapter | Production fails closed for unconfigured/uncertified provider; certified adapter passes configuration gate without changing OpFin product/ledger invariants |
| UAT-34 | Cito-primary identity routing | Configure Cito/gnuGrid NIN and phone checks with no biometric provider | NIN and phone evidence pass through Cito; liveness/face remain pending; overall KYC remains pending rather than being falsely verified |
| UAT-35 | Ambiguous Cito identity failure | Make Cito identity call time out/fail ambiguously while a direct identity provider is configured | Case remains pending with explicit error/reconciliation evidence; direct provider is not automatically called |
| UAT-36 | Explicit direct KYC fallback | After reconciling the prior Cito operation, select the direct route and run the configured evidence-capable provider | Direct provider runs under the explicit route policy; source/route/provider reference and economics evidence remain attributable |
| UAT-29 | Inclusion Financial Space isolation | Attempt to attach capability/support evidence to another user's Space | Request blocked; no cross-Space evidence written |
| UAT-30 | High contrast accessibility | Enable high contrast and complete core journey | Stronger boundaries/contrast apply without breaking layout, labels or touch targets |
| UAT-31 | Screen reader physical-device test | Use VoiceOver/TalkBack on sign-in, Home, resilience, programme and support flows | Controls have understandable semantics, focus order is usable and no critical action is inaccessible |
| UAT-32 | Voluntary programme exit | Enrol, record a capability event, leave the programme twice, then record another capability event | Exit is idempotent; one exit event is recorded; historical participant count remains; post-exit capability event is excluded from that programme’s observation window; automatic re-enrolment is blocked |

## Release decision
Do not sign off with unresolved Critical/High defects in identity, permissions, financial integrity, privacy, money movement, reconciliation or required mobile completeness. Record medium/low exceptions with owner and accepted disposition.

## UAT suite: Impact & Inclusive Finance

### IMP-01 Financial-health separation from credit

**Objective:** Verify financial-health check-ins cannot become underwriting inputs.

**Procedure:**

1. Complete a financial-health check-in as a customer.
2. Confirm the API response returns `is_credit_score=false` and `credit_decision_eligible=false`.
3. Change check-in inputs so the displayed health status changes.
4. Verify no credit score, limit, pricing or approval state changes solely because of the check-in.

**Expected result:** The financial-health status changes transparently while credit decisioning remains unaffected.

### IMP-02 Programme measurement consent

**Objective:** Verify programme-linked outcome capture requires explicit consent and active enrolment.

**Procedure:**

1. With programme measurement consent off, submit a programme-linked health or empowerment observation.
2. Confirm the API rejects it.
3. Turn consent on but remain unenrolled.
4. Confirm it is still rejected.
5. Enrol and repeat.

**Expected result:** Only the consented, actively enrolled state accepts the programme-linked observation.

### IMP-03 Small-cohort privacy

**Objective:** Verify sensitive programme outcome cohorts below five cannot be inferred from partner reporting.

**Procedure:**

1. Assign an indicator to a programme.
2. Record participant observations for one to four distinct participants.
3. Open the partner impact view.

**Expected result:** Participant values, participant count, participant observation count and participant-only latest timestamp are suppressed. Coverage cards display **Suppressed** where the underlying distinct-person count is one to four.

### IMP-04 Programme-partner isolation

**Objective:** Verify a programme partner cannot view unassigned programmes.

**Procedure:**

1. Sign in with a dedicated programme-partner account with no grants.
2. Confirm the programme register is empty.
3. Attempt to open a programme impact endpoint directly.
4. Grant access to exactly one configured partner programme.
5. Confirm only that programme becomes available.

**Expected result:** Access remains programme-scoped and no individual customer records are returned.

### IMP-05 Theory of change and indicator governance

**Objective:** Verify programme strategy can be configured without altering OpFin core decisioning.

**Procedure:**

1. Configure a theory of change.
2. Create and assign an indicator.
3. Confirm the indicator is returned as `credit_decision_eligible=false`.
4. Record participant and institutional observations.
5. Confirm the outcome view distinguishes participant and institutional evidence.

**Expected result:** Programme strategy and MEL can be configured independently of credit policy.

### IMP-06 Causality language

**Objective:** Prevent unsupported impact claims.

**Procedure:** Review admin and partner outcome pages.

**Expected result:** The interface states that measured programme observations do not prove programme causality unless the evaluation design supports causal attribution.

## UAT suite: Programme delivery P0-P2

| ID | Scenario | Expected result |
| --- | --- | --- |
| PGM-01 | Create typed instrument and due baseline | Customer sees only due, channel-enabled instrument |
| PGM-02 | Withdraw measurement consent | Measurement check-ins disappear from customer channels; operations show consent exception |
| PGM-03 | Reviewed locale missing/present | English fallback is explicit; reviewed translation replaces fallback |
| PGM-04 | Submit through App/Web/USSD/WhatsApp/assisted | All write the same response model; assisted actor is separate |
| PGM-05 | Partner invitation activation | Wrong/unverified phone fails; OTP-verified dedicated account succeeds |
| PGM-06 | Partner access/revocation | Only granted programme visible; revoke removes access |
| PGM-07 | CSV/XLSX/ZIP export | Aggregate-only; suppressed cohorts remain suppressed; no participant identifiers |
| COM-01 | Record acquisition + acquisition cost + revenue | CAC/contribution reconcile to recorded events; unknown data remains incomplete |
| COM-02 | Graduation evaluation | Transparent criteria; no credit score/price/limit changes |
| P2-01 | Financial-health enrichment | Uses only recorded OpFin evidence, carries provenance and remains non-credit |
| P2-02 | Adapter activation | Active status blocked until configuration/credentials evidence and legal basis confirmed |
| P2-03 | Adapter ingestion | Non-allow-listed key rejected; accepted evidence is verified but risk-eligible remains false |
