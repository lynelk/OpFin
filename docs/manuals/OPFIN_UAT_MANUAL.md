# OpFin User Acceptance Testing Manual

Status: Controlled internal UAT manual  
Version: 23 September 2026  
Language: English (United Kingdom)

Record tester, exact build/commit, environment, date, evidence and result for every case. Use synthetic data unless an authorised production acceptance plan explicitly requires otherwise.

| ID | Scenario | Procedure | Expected result |
|---|---|---|---|
| UAT-01 | Register person once | App: phone → OTP → names → PIN | Account created; Personal Space available; no duplicate persona |
| UAT-02 | Web sign-in boundary | Open public site and select Web sign-in | Existing/authorised sign-in is clear; site does not present Web password login as preferred new-customer registration |
| UAT-03 | Progressive verification | Use basic feature, then regulated feature | Basic feature avoids unnecessary KYC; regulated journey requests required verification |
| UAT-04 | Multi-Space | Create/join Group and Business | Same identity sees authorised Spaces with distinct context |
| UAT-05 | Isolation | Attempt Personal data access from Group/Employer context | Access denied unless explicitly authorised |
| UAT-06 | Individual mobile completeness | Complete supported everyday-money journeys in App | Normal Individual journey does not require Web |
| UAT-07 | Savings Group mobile completeness | Create/join/manage group context in App | Normal group journey does not require Web |
| UAT-08 | Financial position | Add asset, debt and receivable | Position reflects recorded values deterministically |
| UAT-09 | Safe to spend | Add available money and commitments | Guidance reflects recorded inputs and remains labelled guidance |
| UAT-10 | Web enhancement | Open Spaces/Workspace on Web | Same server-authoritative context; ownership unchanged |
| UAT-11 | Employer | Enable employer on Business Space | Capability enabled without exposing employee Personal Space |
| UAT-12 | Partner catalogue | Load activated products | Only applicable active/territory-appropriate entries appear |
| UAT-13 | Subscription/entitlement | Activate plan | Entitlement remains separate from role and product eligibility |
| UAT-14 | Commercial idempotency | Record same source revenue/cost event twice | Duplicate source reference does not create duplicate event |
| UAT-15 | CPay reconciliation | Reconcile payment/reference | Provider reference and final state remain attributable |
| UAT-16 | Provider failure | Interrupt request/callback and retry | No duplicate money movement; pending/error remains recoverable |
| UAT-17 | Offline | Capture supported offline action and reconnect | Action syncs once; conflicts do not overwrite server truth silently |
| UAT-18 | Low literacy | Complete core App journey | Labels/actions understandable; minimal typing |
| UAT-19 | Accessibility | Text scale/high contrast/screen-reader test | Core journey remains operable; physical-device evidence recorded |
| UAT-20 | Permissions | Member attempts admin action | Blocked; authorised role succeeds |
| UAT-21 | Documentation | Compare routes, clients, website and manuals | Terminology and availability claims agree |
| UAT-22 | Programme measurement opt-in | Save measurement before/after consent | Rejected before consent; accepted only under valid programme state |
| UAT-23 | Programme consent withdrawal | Withdraw measurement consent | New programme measurement stops; normal financial journeys remain |
| UAT-24 | Protected signal boundary | Attempt risk eligibility for protected field | Blocked; protected field never becomes risk input |
| UAT-25 | Governed provider signal | Attempt approved non-protected signal before/after consent/policy | Only passes the separate governed pathway; ingestion alone remains non-risk |
| UAT-26 | Programme eligibility | Test matching/incomplete/non-matching participant | Deterministic programme result; credit state unchanged |
| UAT-27 | Programme privacy | Create cohorts below/above threshold | Small cohort count/values suppressed |
| UAT-28 | Outcome window | Create activity before/during/after enrolment | Programme reporting respects participation window |
| UAT-29 | Alternative collateral | Submit evidence without/with issuer reference | Verification requires evidence; verification does not auto-approve credit |
| UAT-30 | Cito/direct routing | Simulate ambiguous Cito timeout | No silent direct fallback; reconcile then explicitly switch if allowed |
| UAT-31 | Positive employer enrichment | Compare no/negative/verified-positive signal | No/negative is neutral; approved positive benefit is capped |
| UAT-32 | Service economics | Record unknown cost then enrich/reconcile | Unknown stays null until evidence; principal/premium not revenue |
| UAT-33 | Partner reports | Generate capital/insurance/savings/service-economics reports | Totals reconcile; exceptions/incomplete fields remain visible |
| UAT-34 | Direct-provider certification | Try unconfigured/uncertified then certified test adapter | Fails closed until gate satisfied |
| UAT-35 | Cito-primary identity | Run NIN/phone checks without biometric provider | Partial evidence stays partial; KYC not falsely completed |
| UAT-36 | Ambiguous identity failure | Timeout primary route with direct provider configured | Case remains pending/error; direct provider not called silently |
| UAT-37 | Explicit direct KYC fallback | Reconcile prior request then select direct route | Explicit policy/source/provider references retained |
| UAT-38 | Inclusion Space isolation | Attach support/programme evidence to another user's Space | Blocked; no cross-Space write |
| UAT-39 | Voluntary programme exit | Enrol, record, leave twice, record again | Exit idempotent; post-exit events excluded from participation period |
| UAT-40 | Programme partner isolation | Dedicated partner accesses ungranted/granted programme | Only granted programme visible; aggregate data only |
| UAT-41 | Programme instruments | Create typed instrument and due baseline | Only due/channel-enabled instrument appears |
| UAT-42 | Programme localisation | Test reviewed locale missing/present | Reviewed translation used; otherwise English fallback explicit |
| UAT-43 | Multi-channel programme response | Submit via App/Web/verified WhatsApp/USSD/assisted | Same server-authoritative model; assisted actor separated |
| UAT-44 | Partner invitation/revocation | Activate with verified phone, then revoke | Dedicated account activates; revocation removes access |
| UAT-45 | MEL exports | Generate CSV/XLSX/ZIP | Aggregate-only; privacy suppression and notices preserved |
| UAT-46 | Commercial dashboard | Record acquisition/cost/revenue | CAC/contribution reconcile to recorded evidence; unknown remains incomplete |
| UAT-47 | Commercial graduation | Evaluate programme-to-commercial state | Transparent analytics only; no score/price/limit change |
| UAT-48 | Financial-health enrichment | Use recorded OpFin evidence | Provenance retained; output remains non-credit |
| UAT-49 | Adapter activation | Attempt activation without credentials/legal evidence | Blocked until explicit gate satisfied |
| UAT-50 | Adapter ingestion allow-list | Send non-allow-listed then allow-listed key | Unknown key rejected; accepted evidence remains non-risk by default |
| UAT-51 | Website claims | Review marketing homepage against current docs | No guarantee of unavailable provider/service; illustrative preview disclosed |
| UAT-52 | Deployment evidence | Compare commit statuses and workflow runs | Source-deployed vs CI-certified states recorded separately |

| UAT-53 | Location optionality | Use Home/My Money/credit without location permission | Baseline OpFin remains fully usable |
| UAT-54 | Approximate service discovery | Find nearby services and grant coarse/approximate location only | Search works; query coordinates are not stored |
| UAT-55 | Precise purpose gate | Add asset/risk location | Precise permission requested only for that explicit task |
| UAT-56 | Background tracking | Inspect Android/iOS permissions and background behaviour | No background location permission or continuous tracking |
| UAT-57 | Manual fallback | Disable Google Maps/provider and add a location manually | Manual location succeeds; Google-dependent actions fail closed |
| UAT-58 | Place search | Search/select a place with Google enabled | Place ID/address/coordinates returned through server API; key absent from client/API payload |
| UAT-59 | Static map | Open authorised location preview | Small authenticated map renders; unauthorised user is denied |
| UAT-60 | Space isolation | Member/admin/removed-member access to group locations | Member reads, approved admin writes, removed member is denied |
| UAT-61 | Location purpose | Submit mismatched subject/purpose or consent purpose | Rejected; stored purpose/consent stay aligned |
| UAT-62 | Credit boundary | Add/remove/change location context and refresh credit | Credit score/limit/pricing are unchanged by Location Context |
| UAT-63 | Insurance location | Add insured-risk and claim incident locations | Location stored separately; insurer/underwriter remains claim decision authority |
| UAT-64 | Partner network | Partner opens Service network | Only own institution's service points appear; no customer pins |
| UAT-65 | Aggregate geography | Create cohorts below/above five | Below five suppressed; individual user contexts excluded |
| UAT-66 | Account deletion | Delete account after adding personal location | Optional personal location is purged with other optional context |

| UAT-67 | Financial readiness | Call `/api/health/ready` then `/api/health/financial-ready` with a missing required financial dependency | Runtime may remain healthy; financial endpoint returns blocked/503 with non-secret failed checks |
| UAT-68 | Retired credit product | Submit known IDs for paused product/term and attempt offer/acceptance | Rejected at authoritative service boundary; no offer/disbursement created |
| UAT-69 | Invalid payout wallet | Accept valid offer using another user's/unverified wallet | Rejected before offer state/funding mutation; no money intent created |
| UAT-70 | Durable payment intent | Force provider configuration rejection after a money instruction | Canonical local intent remains durable with failed/non-accounting state and same reference |
| UAT-71 | Ambiguous provider submission | Inject timeout/transport ambiguity after intent persistence | Intent remains processing/pending with explicit ambiguous marker; no blind duplicate submission |
| UAT-72 | Manual reconciliation match | Support attempts to mark exception as matched/written off | Rejected; only provider evidence can match |
| UAT-73 | Reconciliation write-off | Maker requests write-off, attempts self-approval, checker approves, maker applies | Self-approval rejected; approved write-off succeeds; payment statement remains exception; complete audit evidence retained |
| UAT-74 | Funding provenance | Generate/accept production-equivalent offer with no approved funding pool | Fails closed when funding-pool assignment is required |
| UAT-75 | Regulated disclosure | Remove licensed entity/licence/address/complaints configuration and attempt production credit | Offer generation blocked before customer commitment |
| UAT-76 | Tax/EFRIS determination | Leave `OPFIN_EFRIS_REQUIRED` unset, then test explicit applicable/not-applicable configurations | Unset is blocked; applicable requires enabled credentials; not-applicable requires documented determination reference |
| UAT-77 | Integrity gate | Create a high/critical financial-integrity exception and run `php artisan opfin:financial-readiness` | Command fails until latest run is balanced and open high/critical alerts are zero |


## Impact and causality acceptance

Programme dashboards and partner exports must state or imply only measured/observed change unless the evaluation design separately supports causal attribution.

Financial-health and programme measurements must remain explicitly separate from credit scoring and pricing.

## Release decision

Do not sign off with unresolved Critical/High defects in identity, permissions, Financial Space isolation, financial integrity, privacy, money movement, reconciliation, provider retry safety, programme-data separation or required mobile completeness.

Record medium/low exceptions with owner, rationale and accepted disposition.

A deployment-success status is not sufficient release evidence when the required exact-head CI/security/deployment gates have not also run.

Financial UAT additionally requires `php artisan opfin:financial-readiness` to pass on the exact candidate environment. Do not use production for destructive/failure-injection UAT.
