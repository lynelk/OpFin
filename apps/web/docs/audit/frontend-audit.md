# OpFin Frontend Audit

## Scope

This audit covers the frontend repository for OpFin. It is documentation-only and does not introduce new screens or business features.

## Current Application State

The repository contains a real Flutter mobile application scaffold in `opfin-frontend/`. This pass also adds a root-level Next.js + TypeScript frontend scaffold for the customer, admin, employer, and future web portal surfaces requested for OpFin.

The Flutter app currently focuses on authentication, onboarding, profile/NIN validation, loan product discovery, loan applications, loan balances, and loan repayment. The new Next.js app is mock-first and uses placeholders where backend contracts are incomplete.

## Current Stack

Web scaffold:

- Framework: Next.js App Router.
- Language: TypeScript.
- Styling: global CSS with shared layout, panel, table, form, badge, and button primitives.
- Route protection: `middleware.ts` using mock role cookies until production auth is integrated.
- API layer: `src/lib/api/client.ts` with mock fallback.
- Navigation: role-aware navigation in `src/lib/navigation.ts`.
- Tests: Vitest tests for the mock API client.

Existing mobile app:

- Framework: Flutter mobile app.
- Language: Dart, SDK constraint `>=3.4.4 <4.0.0`.
- Platforms scaffolded: Android and iOS.
- App package name: `opfin`.
- Android namespace/application ID: `org.rotaryo.opfin`.
- UI approach: Material widgets with a custom light theme in `lib/main.dart`.
- Navigation: imperative `Navigator.push`, `Navigator.pushReplacement`, and `MaterialPageRoute`.
- HTTP: direct `package:http` calls inside screen widgets.
- Local storage:
  - `flutter_secure_storage` for access token, user ID, phone, national ID, date of birth, and NIN status.
  - `shared_preferences` for display fields and onboarding flag.
- Assets: Lottie animations under `assets/lottie/`.
- Linting: `flutter_lints` through `analysis_options.yaml`.

## Installed Packages

Runtime dependencies:

- `http`
- `intl`
- `lottie`
- `shared_preferences`
- `flutter_secure_storage`

Development dependencies:

- `flutter_test`
- `flutter_lints`
- `flutter_launcher_icons`

## Routing Structure

The app uses `home: const SplashScreen()` in `MaterialApp` and does not define named routes, route guards, deep links, typed route arguments, or a router package.

Primary observed flow:

- `SplashScreen`
  - Sends returning authenticated users to `HomeScreen`.
  - Sends unauthenticated users to `LoginScreen`.
  - Sends first-time users to `OnboardingScreen`.
- `OnboardingScreen`
  - Sends users to `RegisterScreen`.
- `RegisterScreen`
  - Requests OTP generation and sends users to `OtpScreen`.
- `OtpScreen`
  - Verifies OTP.
  - Registers users or resets password depending on mode.
  - Returns users to `LoginScreen`.
- `LoginScreen`
  - Logs in users and sends them to `HomeScreen`.
  - Links to `ForgotPasswordScreen` and `RegisterScreen`.
- `HomeScreen`
  - Loads loan applications and loan balance.
  - Links to `ProductsScreen`, `LoanApplicationsScreen`, and profile-related areas.
- `ProductsScreen`
  - Loads products and sends users to `ProductTermsPage`.
- `ProductTermsPage`
  - Loads product terms and sends users to `LoanApplicationScreen`.
- `LoanApplicationScreen`
  - Collects application data.
  - Sends users through confirmation/result screens.
- `LoanApplicationsScreen`
  - Lists applications and can send users to `LoanRepaymentScreen`.
- `ProfileScreen`
  - Shows locally stored profile fields.
  - Supports NIN validation and logout.

## API Client State

The frontend has no centralized API client. Each screen constructs URLs from `lib/constants.dart` and calls `http.get` or `http.post` directly.

Current base URL:

- `https://app.opfin.co/api`

A local LAN URL is commented in `constants.dart`, but environment selection is not formalized.

Current API usage includes:

- `POST /login`
- `POST /logout`
- `POST /generate-otp`
- `POST /verify-otp`
- `POST /register`
- `POST /reset-password`
- `GET /loan-applications/{userId}`
- `GET /loan-balance/{userId}`
- `POST /loan-applications`
- `POST /loans/{loanId}/repay`
- `GET /products`
- `GET /product-terms/{productId}`
- `POST /validate-nin`

## Authentication Requirements

The app expects token-based API authentication and sends `Authorization: Bearer <token>` on protected endpoints. This aligns with the Laravel backend foundation using Sanctum tokens, but the frontend still needs a proper API layer that can:

- Attach bearer tokens consistently.
- Handle `401` responses by clearing the session and returning to login.
- Normalize backend validation errors.
- Handle token missing/expired states.
- Avoid logging tokens, NIN, dates of birth, or phone numbers.
- Keep user identity and role assumptions server-driven.

## Missing Setup

- No centralized API client, repository layer, or typed response/error model.
- No formal environment configuration for development, staging, demo, and production.
- No named routes, route guards, deep links, or typed routing.
- No app-wide state management beyond `setState`, secure storage, and shared preferences.
- No test directory for Flutter widget/unit tests was found.
- No committed CI baseline was confirmed for analyze/test/build in this audit; the working tree contains uncommitted `.github/` content that should be reviewed separately.
- Root README is minimal and nested Flutter README is still default scaffold text.
- No crash reporting, analytics, performance monitoring, or release observability setup.
- No accessibility, localization, or Uganda-specific formatting baseline beyond limited use of `intl`.
- No design system beyond shared button/input helpers and the global theme.
- No feature flags or demo-mode separation.
- No explicit privacy/consent UX foundation.
- No documented API contract between frontend and backend.

## Boilerplate Still Present

- `opfin-frontend/README.md` still says "A new Flutter project."
- `pubspec.yaml` description is still "A new Flutter project."
- Some generated platform scaffold files remain standard Flutter defaults, which is normal, but product documentation and release metadata need cleanup.

## Required Product Screens

The current app covers a basic credit flow but needs a broader OpFin screen map before new implementation:

- Auth: login, registration, OTP verification, password reset, logout.
- Onboarding: customer onboarding, consent capture, KYC introduction.
- Home: personalized dashboard, credit summary, obligations, next action.
- Profile: personal details, verified identity status, security settings.
- KYC: NIN verification, document/selfie capture if required, review status, failure recovery.
- Consent center: mobile money consent, CRB consent, employer consent, data sharing history.
- Credit: products, eligibility, application, review, approval status, disbursement status, repayment schedule, repayment confirmation.
- Savings: goals, wallet balances, deposits, withdrawals, history.
- Investments: product discovery, suitability acknowledgement, portfolio view, statements.
- Insurance: product discovery, policy enrollment, claims status.
- Employer-linked benefits: employer dashboard entry, employee benefit status, salary advance eligibility.
- Support: FAQs, tickets, disputes, complaints, escalation.
- Notifications: alerts, repayment reminders, compliance notices.
- Compliance: data export, account closure, consent withdrawal, regulatory notices.

## Investor Demo Needs

Investor demos should be isolated from live financial operations. Recommended demo surfaces:

- Demo dashboard showing responsible credit, savings, investments, insurance, and employer benefits as one platform.
- Responsible credit flow using sandbox products and explicit demo labels.
- KYC and consent trust flow showing how customer permission gates CRB, mobile money, and employer data access.
- Repayment and mobile money simulation that never calls live integrations.
- Employer benefits concept screen with sample employer and employee eligibility.
- Compliance/audit story screen showing consent history, audit logging, and regulatory readiness.
- Roadmap/coming-soon cards for savings, investments, and insurance until backend support exists.

## Readiness Assessment

The frontend is buildable as a Flutter product directionally, but it is not ready for 1,000+ users or investor demos without hardening. The next work should be foundation work: API client, routing, auth guards, environment config, typed models, error handling, tests, observability, and a documented screen map.
