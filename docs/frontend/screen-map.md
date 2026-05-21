# Frontend Screen Map

## Existing Screens

The current Flutter app contains these observed screens and UI modules:

- `SplashScreen`: checks onboarding/session state and routes to onboarding, login, or home.
- `OnboardingScreen`: intro carousel/flow before registration.
- `LoginScreen`: phone/password login.
- `RegisterScreen`: registration form and OTP request.
- `OtpScreen`: OTP verification, registration continuation, and password reset completion.
- `ForgotPasswordScreen`: initiates password reset OTP flow.
- `HomeScreen`: customer home with loan applications and loan balance data.
- `ProductsScreen`: lists available loan products.
- `ProductTermsPage`: lists terms for a selected product.
- `LoanApplicationScreen`: captures loan application details.
- `LoanConfirmationScreen`: displays loan application confirmation details.
- `LoanApplicationResultScreen`: displays application success/failure state.
- `LoanApplicationsScreen`: lists customer loan applications.
- `LoanRepaymentScreen`: submits repayment for a loan.
- `ProfileScreen`: displays profile/session data, supports NIN validation and logout.
- `FaqsScreen`: static loan FAQ content.
- `ApplicationForm`, `LoanAmountScreen`, `LoanDetailsScreen`, and `LoanReviewScreen`: earlier/static loan flow components that may need consolidation with the active API-backed flow.

## Current Routing Map

The app starts at `SplashScreen` through `MaterialApp.home`.

Observed route flow:

```text
SplashScreen
  -> OnboardingScreen
      -> RegisterScreen
          -> OtpScreen
              -> LoginScreen
  -> LoginScreen
      -> ForgotPasswordScreen
          -> OtpScreen
              -> LoginScreen
      -> RegisterScreen
      -> HomeScreen
  -> HomeScreen
      -> ProductsScreen
          -> ProductTermsPage
              -> LoanApplicationScreen
                  -> LoanConfirmationScreen
                  -> LoanApplicationResultScreen
      -> LoanApplicationsScreen
          -> LoanRepaymentScreen
              -> HomeScreen
      -> ProfileScreen
          -> LoginScreen
```

## Routing Gaps

- No named route registry.
- No route guards.
- No deep links.
- No typed route arguments.
- No bottom navigation or app shell documented for future modules.
- No explicit separation between unauthenticated, customer, employer admin, support, operations, and platform admin screens.

## Required Screens Before Product Expansion

Before implementing savings, investments, insurance, employer benefits, CRB, or mobile money integrations, define these screens:

- Auth guard and session restore states.
- Profile from backend `/profile`, not only local storage.
- Consent center with active, revoked, and expired consents.
- KYC checklist and verification status.
- Credit eligibility and responsible borrowing education.
- Loan schedule and repayment detail.
- Mobile money authorization status and repayment confirmation.
- Transaction history.
- Notifications and repayment reminders.
- Support ticket and complaint flow.
- Settings and security controls.

## Future Module Screens

Credit:

- Product catalog.
- Eligibility result.
- Application draft/review.
- Decision status.
- Disbursement status.
- Repayment schedule.
- Repayment receipt.

Savings:

- Savings dashboard.
- Goal creation.
- Deposit and withdrawal flow.
- Statement/history.

Investments:

- Investment product catalog.
- Suitability/risk acknowledgement.
- Portfolio summary.
- Transaction history.

Insurance:

- Insurance product catalog.
- Policy enrollment.
- Policy details.
- Claims flow.

Employer-linked benefits:

- Employer benefits landing screen.
- Employee eligibility.
- Benefit request.
- Employer approval/status.

Compliance and trust:

- Consent center.
- Data sharing history.
- Data export request.
- Account closure request.
- Regulatory notices.

## Investor Demo Screens

Recommended investor demo screens should be flagged as demo-only and backed by sandbox data:

- Platform overview dashboard.
- Customer trust journey: onboarding, KYC, consent.
- Responsible credit journey: eligibility, application, decision, repayment.
- Employer-linked benefits preview.
- Savings/investments/insurance preview with clear "not live" labeling.
- Compliance/audit preview showing consent trail and sensitive-action history.

