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
| UAT-29 | Inclusion Financial Space isolation | Attempt to attach capability/support evidence to another user's Space | Request blocked; no cross-Space evidence written |
| UAT-30 | High contrast accessibility | Enable high contrast and complete core journey | Stronger boundaries/contrast apply without breaking layout, labels or touch targets |
| UAT-31 | Screen reader physical-device test | Use VoiceOver/TalkBack on sign-in, Home, resilience, programme and support flows | Controls have understandable semantics, focus order is usable and no critical action is inaccessible |

## Release decision
Do not sign off with unresolved Critical/High defects in identity, permissions, financial integrity, privacy, money movement, reconciliation or required mobile completeness. Record medium/low exceptions with owner and accepted disposition.
