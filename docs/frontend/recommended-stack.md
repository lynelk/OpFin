# Recommended Frontend Stack

## Recommendation

Continue with the existing Flutter mobile application as the primary OpFin customer frontend. The repository is already scaffolded and contains real screens, so replacing it with Next.js would slow mobile delivery without solving the current architecture gaps.

Use Next.js + TypeScript only for future web surfaces such as investor demos, employer admin, operations, support, and compliance dashboards. Do not introduce that web app until the mobile foundation and backend API contracts are stable.

## Mobile App Stack

Recommended retained stack:

- Flutter stable channel.
- Dart 3.4+ initially, then upgrade on a controlled schedule.
- Material-based UI with a documented OpFin design system.
- Android and iOS release targets.
- `flutter_lints` plus stricter project rules over time.

Recommended additions:

- Routing: `go_router` for named routes, auth guards, deep links, and typed route paths.
- State management: Riverpod or Bloc. Riverpod is recommended for a pragmatic team-friendly foundation.
- API client: Dio or a wrapped `package:http` client. Dio is recommended if interceptors, retries, upload progress, and richer error handling are needed.
- Models: typed request/response DTOs with generated JSON serialization.
- Secure storage: keep `flutter_secure_storage` for tokens and sensitive identity fields.
- Environment config: Flutter flavors or compile-time dart defines for local, staging, demo, and production.
- Observability: crash reporting and structured client-side error logging before production.
- Testing: unit tests for API/session/domain logic, widget tests for core screens, and integration tests for auth and credit flows.

## API Layer Standards

Introduce one API boundary before adding more product modules:

- `ApiClient` owns base URL, headers, timeout, auth token attachment, response decoding, and error normalization.
- Feature services own endpoint-specific methods, such as `AuthApi`, `ProfileApi`, `CreditApi`, and `KycApi`.
- UI widgets should not directly call `http.get` or `http.post`.
- API responses should match the backend standard envelope.
- All money values should be handled as integer minor units in models and formatted only at the UI edge.

## Authentication Standards

The frontend should align with Laravel Sanctum token authentication:

- Store bearer tokens only in secure storage.
- Attach tokens through the API client, not by hand in each screen.
- Clear session and redirect on `401`.
- Avoid storing sensitive PII in `SharedPreferences`.
- Treat roles and permissions as server-provided and refresh them from the profile endpoint.
- Add route guards for authenticated and unauthenticated sections.

## Demo and Production Separation

Investor demo behavior must be separated from production behavior:

- Use a dedicated demo environment or feature flag.
- Clearly label demo data.
- Never point demo flows at live mobile money, CRB, employer payroll, investment, or insurance integrations.
- Ensure demo state cannot create unaudited financial records.

## If a Web Frontend Is Added Later

Recommended web stack:

- Next.js App Router.
- TypeScript.
- Tailwind CSS or a disciplined component system.
- Server-side auth integration with the Laravel API.
- Separate web routes for investor demo, employer admin, operations, support, and compliance.

Do not build this yet inside the current Flutter frontend repository unless the product decision is to create a monorepo.

