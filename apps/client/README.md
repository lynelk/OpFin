# OpFin mobile application

Status: Controlled external developer/product reference  
Reviewed: 21 October 2026  
Language: English (United Kingdom)

The Flutter application is OpFin's primary customer mobile experience. It presents a mobile-complete financial journey while the API remains authoritative for identity, permissions, eligibility, money, credit, provider finality, programme state, ledger and reconciliation.

## Product position

The customer proposition is to understand, manage, plan, protect and improve money. Borrowing is one capability rather than the application boundary. The signed-in customer lands in the Personal context; Financial Spaces extend that financial life rather than replacing it.

One person may access authorised Personal, Household, Savings Group, Investment Club, SACCO and organisation Financial Spaces without creating separate identities.

## New-customer journey

`Phone → OTP → names → six-digit PIN → authenticated Home`

- Android OTP auto-fill uses SMS Retriever/app-signature support where available; manual entry remains possible.
- Legacy password compatibility is a migration concern, not the preferred mobile UX.
- Sign-in, registration, OTP, account-completion and PIN-reset screens use the current OpFin monogram and shared brand tokens rather than generic financial icons.
- A second phone is optional.

## Personal Home and everyday money

Home is personal-finance first. It uses the server-authoritative Financial Compass to show recorded available money/safe-to-spend, savings, debt, upcoming obligations, cash-flow context and one useful next action before presenting product choices.

For routine refresh, the App uses the authenticated aggregate `/api/mobile/home` contract so Financial Compass, credit-profile state, protection policies and Financial Spaces arrive in one measured request. A private in-memory ETag allows unchanged refreshes to return `304` without retransmitting the financial payload. The former multi-request sequence remains a temporary compatibility fallback only for older API deployments.

**My Money** supports current cash/mobile-money/bank balances and personal debt planning. A debt recorded in the person's Personal Space contributes to the Financial Compass and, when it has a due date, the upcoming commitment view. OpFin-originated loan schedules remain server-derived; customers do not have to re-enter them manually.

Protection is available as a normal personal-finance destination. The App distinguishes premium initiation, partner settlement, insurer issuance and active cover, and routes claim decisions to the disclosed insurer/underwriter.

## Financial Spaces and everyday money

Individuals and Savings Groups should be able to complete normal everyday journeys in the App without Web being a hidden prerequisite. Investment Club members use the same App and identity. Group/SACCO administrators can use the Web Workspace for deeper administration, but member participation is not split into another consumer app.

### Provider statements

When the server reports that Financial Intelligence is available for a Space, a **Provider statements** card appears on that Space's screen. Otherwise the card is hidden rather than advertised. From there a customer can:
- upload a bank or mobile-money statement (PDF or CSV) through the system document picker, which needs no storage or media permission;
- see the server's plain-language results: whether it was read, whether balances add up, that the provider has not confirmed it, the review state and the next step;
- appeal a rejection once, upload a replacement when a reviewer asks, or stop the statement being used.

The upload screen reminds customers that OpFin never asks for a statement password, PIN or OTP. All calls go through the metered `OpFinHttp` client under the `statements` feature. Retried uploads and appeals reuse one idempotency key, so nothing is recorded twice. No wording describes a document or customer as suspicious.

The App supports server-authoritative Space context and financial-life features such as money/accounts, budgets, goals, assets, liabilities/receivables, net position and supported financial-health guidance. Savings Groups, Investment Clubs and SACCOs can attach external government/regulator identifiers to their existing Space record as those schemes become applicable. Group protection catalogues are visible only for approved group-capable products; group enrolment and premium collection remain fail-closed until separately activated.

## Lightweight location context

Location is optional and task-driven.

The App uses native Android/iOS foreground location rather than embedding a full Google Maps SDK. Approximate location is the default for service discovery. Fine/precise permission is requested only for a location-dependent asset, insured risk or claim task.

Customers can also search for a place through the server-side Google Maps adapter or enter a place manually. Small Static Map previews are loaded through the authenticated OpFin API so the Google server API key is never embedded in the client.

Current App entry points include:

- participating services near me;
- Saving Group / Investment Club / SACCO operating area;
- group meeting place;
- physical asset/project location;
- insured-risk location; and
- claim incident location.

Android requests ACCESS_COARSE_LOCATION first and ACCESS_FINE_LOCATION only for precise tasks. iOS requests When In Use access. Background location is not configured.

## Data, storage and low-connectivity behaviour

All ordinary OpFin API traffic is routed through the metered mobile network layer. The App records aggregate application-layer bytes by feature and operation without storing request or response payloads, identity numbers, PINs, OTPs, tokens or raw financial content.

Under **More → Data & storage**, customers can see this month's OpFin application traffic and whether each measured byte falls inside an operator-confirmed sponsored boundary, outside that boundary, or has billing treatment that is not yet confirmed. OpFin does not describe traffic as free merely because it used an OpFin hostname; sponsorship eligibility is bound to the approved scheme, host and effective port, and the mobile operator remains authoritative for billing/zero-rating.

Offline actions use bounded queues and bounded sync batches. Oversized or sensitive actions are not silently stored in the generic offline queue. KYC capture resizes/compresses mobile images and applies a total mobile upload budget while preserving the requirement for legible evidence.

Client-facing hosts are controlled by `distribution/sponsored-data/whitelist-manifest.json`. New SDKs, media sources, direct provider hosts, polling loops or background traffic require a data-impact and whitelist review before release. See `../../docs/architecture/LIGHTWEIGHT_SPONSORED_DATA.md`.

## Identity verification

Where a selected service requires identity evidence, the App follows the configured KYC contract, including NIN and required document/selfie evidence.

The App must not request broad SMS/gallery/storage permissions merely to support personal-loan decisioning.

Customers who cannot complete normal capture because of an accessibility need can request assisted verification. Assistance never means sharing a PIN or OTP or lowering identity assurance.

## Responsible credit

The App reads server-authoritative credit profile, available-to-borrow amount, amount due/total outstanding, next due date, next action, eligible terms, formal offer disclosures and verified wallet choices.

A profile limit is not guaranteed approval. Pending provider requests are not completed payments or disbursements. Completed provider-finality-backed events may create auditable receipts under **Activity**.

### Salary-linked payroll deduction

Android and iOS share one Flutter payroll-deduction journey and the same API state. The experience covers affordability, customer undertaking, reservation, Key Facts/vote approval, payroll submission, rejection/amendment and payment reconciliation.

The navigation entry is gated by `OPFIN_PAYROLL_DEDUCTION_ENABLED` and is off by default. It must be enabled only after the salary-finance product and operating route are approved. The customer can still see that a live provider machine connection is inactive when the screen is opened in a controlled test build; the client never contains PDMS credentials or invents provider endpoints.

For an eligible `salary_finance` application, the customer may start a payroll case, review the verified affordable deduction, explicitly authorise the payroll undertaking and request a reservation. The requested deduction cannot exceed the verified affordable amount. Vote approval and payroll feedback remain server/operations-controlled. A successful payroll result is displayed as reconciliation pending until the expected and recovered amounts match.

The shared client version for this change is `1.0.2+20`. Android and iOS release builds must both pass the monorepo CI gates before distribution.

## Financial resilience and inclusive finance

The App supports financial-health guidance, a financial-reputation stage that is not a second credit score, optional programme-measurement consent, due programme check-ins, reviewed translations with explicit English fallback, voluntary programme exit, alternative credit-support evidence and optional recorded-data health enrichment.

Programme/protected attributes remain outside underwriting. Programme outcomes are not causal claims by default.

Stolets remains a separate SME automation and commerce product; approved external evidence must arrive through explicit governed integration.

## Accessibility

Supported design requirements include TalkBack/VoiceOver semantics, operating-system text scaling, optional larger-text/simple-language preferences, reduced motion, high contrast, practical touch targets and assisted verification paths.

Certification-level accessibility claims require physical-device acceptance evidence.

## Android compatibility and identity

The Android build currently requires compile SDK 37, targets SDK 36 and retains Flutter's configured minimum Android version.

Camera features must remain optional for installation so devices without a rear camera are not filtered merely because the App requests camera permission.

The existing Play application ID is `org.rotaryo.opfin`. The Kotlin namespace remains `co.opfin.app`. Distribution builds must use the registered upload key.

The document picker (`file_picker`) declares no Android permissions: only a package-visibility query for the system "get content" intent. Keep it that way. The release contract forbids storage and media permissions.

## iOS compatibility

The iOS minimum is 14.0. It was raised from 13.0 on 4 October 2026 because the system document picker for provider statements requires iOS 14. Every device that runs iOS 13 can also run iOS 14.

## Development

```bash
flutter pub get
flutter analyze
flutter test
flutter run
```

Run the Android release-contract guard before distribution:

```bash
python3 scripts/verify-android-release-contract.py
```

Never place provider secrets in Flutter.

## Documentation

Start with `../../docs/CURRENT_STATE.md`, `../../docs/product/OPFIN_PRODUCT_BLUEPRINT.md`, `../../docs/manuals/OPFIN_USER_MANUAL.md`, `../../docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md`, `../api/docs/api/API_QUICK_REFERENCE.md`, `../api/docs/api/PAYROLL_DEDUCTION.md` and `../../SECURITY.md`.

Run `make publication-check` before externally publishing product/developer documentation.

## Credit distribution channels

Credit options, applications and Essentials send the actual channel through `lib/services/distribution_channel.dart`. iOS infers `app_store`, Android infers `play_store`, and web infers `web`. Build Huawei Android with `--dart-define=OPFIN_DISTRIBUTION_CHANNEL=huawei_appgallery`; configure actual store policy before publication. Invalid channel/platform combinations are rejected. The channel flag does not replace Huawei device/service integration testing or store approval.

Repayment terms come from the server's configurable lender/channel rules. The client no longer hides terms using a universal 61-day filter or prefers a universal 90-day product. Options/review/offers identify the actual lender; OpFin is the orchestration platform. Home displays an OpFin Score only when the server supplies one. Flutter analysis/tests, Android/iOS release compilation and device UAT remain required. See [the lender contract](../../docs/architecture/LENDER_ORCHESTRATION.md).

## Release-candidate contract update

Read the current payroll/referral/account-deletion tables in `apps/api/docs/api/frontend-backend-contract.md`. Full deletion is immediately rejected while obligations remain; optional deletion keeps the account. Payroll undertaking and reservation are atomic and case-bound. External reservation release is evidenced before closure. No production activation is implied.

## Club accounting client recovery and export

Investment Club members can recover saved accounting requests and retain access to their own historical member statements after leaving a club. Native statement export supports controlled save/share and HTML print flows; exports contain document bytes rather than authentication credentials. Android combines this with the approximate-location-only Play policy, while iOS uses the native print/share bridge. Financial mutations remain server-authoritative and maker-checker controls are not bypassed by the client.



## Financial Spaces and Savings Groups (20 October 2026)

The OpFin mobile app now has a **Switch workspace** action from Home and from each Financial Space detail screen. Only currently active memberships returned by `GET /api/financial-spaces` are offered. Roles and capabilities displayed by the UI come from server-issued `workspace` metadata; absent metadata defaults to **no write or management access**. Selection is a route-local session operation, not a locally persisted tenant identifier.

Workspaces include Personal, Household, Business, Savings Groups (`savings_group` persisted type), SACCOs and Investment Clubs. Selecting a Space opens its own financial position, assets, obligations and the shared monthly budget manager. A non-manager can view authorised information without edit controls. All mutations independently enforce server authorisation.

The budget view presents planned limits only. Recorded expense actuals and reconciliation are **not** claimed by the budget UI. The existing personal finance navigation remains unaffected when opening an organisation context.

OpFin People connections appear within authorised Business Spaces and under More > My employment. This is membership discovery, **not** the completed People product; the ten-module People Core Release remains implementation pending and its full embedded experience must be tested before production.

Mobile regression requirements: switch Personal → Savings Groups → SACCO → Investment Club → Business and back; ensure private financial data never carries across Spaces, inactive/suspended Spaces are absent, unauthorised actions never appear and backend writes remain forbidden; verify app logout, token expiry, offline handling and existing Borrow, Home, Activity and More journeys. Run Flutter analyse/tests for this feature before merging.

Employees who have People-only business membership are routed to their authorised employer connection instead of the organisation's financial position. This prevents accidental exposure of corporate balances through general workspace switching.


## Savings Groups embedded mobile workspace (21 October 2026)

Financial Spaces now links a Savings Groups operational workspace for active members and officials. An official can create a draft savings cycle, a separate official can activate it, members can pledge contributions and request support or loans, and officials can close a cycle and prepare proposed share-outs. Contribution verification requires a separately statement-matched exact amount; non-final evidence never becomes money automatically.

The app labels these records as pledges, requests and proposals until authoritative confirmation. This is **not** a completed end-to-end regulated savings or lending product; physical meeting quorum, actual loan funding, interest allocation, social-fund payout and share-out settlement remain dedicated acceptance tasks. Test offline/retry safety, exact-Space ownership and denial paths on Android and iOS.


## Embedded OpFin People workspace (21 October 2026)

Under More > My employment and eligible Business Financial Space navigation, authorised users enter `PeopleHubScreen` without leaving the OpFin Flutter app. The hub has real scoped API views for workforce engagements, leave, attendance, asset custody, KPIs, appraisals, rewards, field assignments, payslips, payroll periods (payroll roles) and pending biometric connectors (HR roles). Approved actions issue authenticated API requests; absent/inactive memberships cannot view the employer's work records.

Worker engagement ID, user ID and employer-owned asset ID are currently entered through operational forms; these inputs need a reviewed picker/workflow before consumer-grade UAT. Payroll uses manual amounts and does **not** yet compute certified statutory deductions; biometric connectors cannot ingest biometric templates or device attendance while pending legal/vendor validation. No action may be displayed as a completed financial transfer solely because HR has approved it.

Acceptance must include physical-device navigation, employer/employee role privacy, multi-business switching and sign-out, short/long sessions, offline queued attendance with replay resistance, API tests, localisation and accessibility; automated smoke tests do not substitute for UAT.

## Current source journeys and mobile acceptance (21 October 2026)

The App source includes Personal Financial Life, aspirations/learning, club accounting and saved-request recovery, own capital history after leaving a club, protected native statement save/share/print, and a Financial Intelligence statement journey where explicitly enabled. The same user can participate in several Financial Spaces without duplicating identity or granting one Space access to another.

A recoverable club request must use its original identity/hash; a repayment timeout is not permission to collect again. Exporting a statement to a device never transfers bearer credentials into a native export call, and opening a share/print dialogue is not confirmed delivery. Recognised issuer layouts or parsed statements must not be shown as officially authenticated unless independent validation exists.

Run Flutter analysis, tests, platform release compilation, actual-device accessibility, permissions, document-picker, interruption and cross-account checks before claiming a mobile release. GitHub/API deployment statuses do not prove a current App Store or Play Store build. See [source-to-release evidence](../../docs/operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md), [club client workflows](../../docs/manuals/CLUB_ACCOUNTING_CLIENT_WORKFLOWS.md) and [role-based training](../../docs/manuals/ROLE_BASED_TRAINING_PATHS.md).
