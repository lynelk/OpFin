# OpFin

OpFin is the canonical monorepo for the OpFin personal-finance platform: Laravel API, Next.js web experience and Flutter mobile application. The launch customer journey is intentionally simple; identity, credit, ledger, reconciliation and provider controls remain behind the interface.

## Start here

| Need | Reference |
| --- | --- |
| Understand the product | [Launch customer journey](docs/LAUNCH_CUSTOMER_JOURNEY.md) |
| Find any documentation | [Documentation hub](docs/README.md) |
| Set up development | [Developer start here](docs/DEVELOPER_START_HERE.md) |
| Use the API | [Integrator guide](apps/api/docs/api/INTEGRATOR_GUIDE.md), [API quick reference](apps/api/docs/api/API_QUICK_REFERENCE.md) |
| Train customers or staff | [Training foundation](docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md), [learning paths](docs/manuals/TRAINING_PATHS.md) |
| Make a safe change | [Contributing](CONTRIBUTING.md), [engineering rules](AGENTS.md), [security](SECURITY.md) |

## Launch borrower journey

`Phone → OTP → names → 6-digit PIN → Home → identity verification → credit profile → available limit → loan request → formal offer → verified-wallet disbursement → repayment`

The primary mobile navigation is **Home | Borrow | Activity | More**. A second phone is optional. Identity verification captures NIN, National ID front/back and a photo of the customer holding the ID. Source checks must be recorded accurately: unavailable provider results must not become invented scores or verification claims.

The customer sees the OpFin Composite Score, understandable explanations, available limit, amount due and next payment date. Internal probability-of-default measures remain internal. Credit limits are profile-level; adding phones or wallets does not multiply exposure.

App, WhatsApp and USSD use the same server-authoritative state. High-impact actions require authenticated confirmation. Never request a customer's OpFin PIN in WhatsApp or USSD. Accessibility, including large text, screen-reader support, reduced motion and assisted verification, must retain the same security and identity assurance.

Provider- or regulator-gated products stay out of primary launch navigation until genuinely activated. Source implementation is not proof of live availability.

## Repository layout

| Path | Responsibility |
| --- | --- |
| `apps/api` | Identity, consent, eligibility, credit, obligations, money movement, ledger, reconciliation, worker and scheduler |
| `apps/web` | Next.js customer and operational interfaces |
| `apps/client` | Flutter Android/iOS customer application |
| `packages/contracts` | Shared-contract guidance and generated-reference conventions |
| `infrastructure/railway` | Service-boundary and deployment documentation |
| `docs` | Product, cross-platform, training, architecture and release references |

Historical imports remain in Git history. This repository is the current working source; a deployed release can differ from `main` until the release process completes.

## Financial and security boundaries

The API owns financial decisions and accounting state. CPay remains the production money-movement boundary unless an approved architecture change says otherwise. Provider acknowledgement is not financial finality. KYC evidence belongs in private persistent/object storage, and provider secrets never belong in browser or mobile builds.

Store-distributed personal-loan terms retain the repository's 61-day minimum full-repayment control and preference for eligible 90-day-plus routes. Credit reporting, complaints, NPL/default-interest controls, receipts, guarantor confirmation and governed term changes are backend responsibilities. See [digital-lending controls](docs/UMRA_DIGITAL_LENDING_CONTROLS.md) for the implementation reference, not an independent certification of legal compliance.

## Developer and documentation commands

```bash
make help
make docs-search QUERY="credit reporting"
make api-search QUERY="receipts"
make docs-test
make docs-check BASE=origin/main
make docs-build
make docs-serve
```

Documentation search does not need PHP or a database. API route search needs the local API dependencies, or an explicitly supplied route snapshot. The browser-searchable portal is generated at `.build/docs/index.html`; the optional server binds only to `127.0.0.1:8008` and serves the generated directory.

Run `make api-test`, `make web-test`, `make client-test`, or `make test` for affected implementation checks. Documentation tooling is also exercised by the release-gated CI workflow.

## Documentation stays with the change

Update relevant current documents and tests in the same PR as changes to routes, payloads, validation, authorisation, workflows, dependencies or deployment controls. The generated route reference comes from Laravel and reports narrative coverage gaps; it does not invent a complete OpenAPI schema.

Training manuals and user guides derive from the [training foundation](docs/TRAINING_AND_USER_GUIDE_FOUNDATION.md), verified application labels and current contracts, using the [task template](docs/manuals/TASK_GUIDE_TEMPLATE.md). Preserve historical evidence without presenting it as current operating instructions.

[Documentation maintenance](docs/DOCUMENTATION_MAINTENANCE.md) explains the inventory, source provenance, checks and their limits. A passing build is not evidence of activated providers, store publication, real-device accessibility or production acceptance.
