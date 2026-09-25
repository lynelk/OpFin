# Current API endpoints

Status: Current API navigation and contract index  
Reviewed: 25 September 2026  
Language: English (United Kingdom)

The complete existing domain narrative has been preserved without content loss in [Domain endpoints](domain-endpoints.md). It remains the detailed reference for the established authentication, financial-life, credit, programme, treasury and operational routes. The new developer/AI-discovery interface is described below and in [Developer interface](DEVELOPER_INTERFACE.md).

For exact runtime registration, use `php artisan route:list --json` from `apps/api`, or the role-filtered Developer Centre at `/developers` on a deployment containing this change. Registration, reviewed schemas, authorisation, provider activation and financial acceptance remain different states.

## Developer and AI discovery

| Method | Endpoint | Purpose and access |
| --- | --- | --- |
| GET | `/api/developer/manifest` | Public links, source fingerprints and discovery limitations |
| GET | `/api/developer/public` | Explicitly published unauthenticated documentation |
| GET | `/api/developer/guides` | Search public learning guides using `q` |
| GET | `/api/developer/guides/{guide}` | Read an allow-listed guide, not a filesystem path |
| GET | `/api/developer/catalogue` | Sanctum-authenticated, role-filtered operation search |
| GET | `/api/developer/operations/{operation}` | Read one visible operation's contract/readiness state |
| GET | `/api/developer/openapi` | Raw OpenAPI 3.1.1 for reviewed visible operations |
| GET | `/api/developer/agent-tools` | Metadata for four read-only documentation tools |

The catalogue accepts `q`, `page`, `limit`, `group` and `method`. Query text is capped at 160 characters, page at 1–1000 and page size at 1–50. Authentication and role come from the server user. A caller cannot elevate visibility by adding `role=platform_admin` or a different Space to the query.

The new responses use `success`, `message` and `data`, except `/openapi`, which returns a raw specification document. Existing HTML/CSV endpoints, framework/proxy errors and provider callbacks must be handled according to their own contracts. Do not read the older domain narrative's general envelope statement as a promise that every possible response has one shape.

The local [MCP bridge](../../../../tools/opfin-mcp/README.md) can search APIs, describe operations, search guides and read guides. It never dispatches a native financial operation. Models do not gain authority to approve credit, move funds or change accounting by reading a contract.

## 1. Health

See [health operations](domain-endpoints.md#1-health). Readiness and liveness are distinct; a process responding does not establish provider activation or complete financial acceptance.

## 2. Account authentication

See [authentication, registration and deletion contracts](domain-endpoints.md#2-account-authentication). New App registration remains phone → OTP → names → six-digit PIN; migrated Web-password compatibility is separate.

## 3. Phone numbers and wallets

See [phone and wallet contracts](domain-endpoints.md#3-phone-numbers-and-wallets). Ownership and profile-level exposure are enforced by the domain service, not by documentation discovery.

## 4. Identity and consent

See [identity and consent](domain-endpoints.md#4-identity-and-consent) and [governed internal NIN evidence](../../../../docs/architecture/IDENTITY_EVIDENCE_REUSE.md). Approved fresh evidence can be reused through the implemented Cito NIN path; default policy settings do not activate it. No direct NIRA agreement or universal KYC cache is implied.

## 5. Credit profile

See [credit profile](domain-endpoints.md#5-credit-profile). Unavailable evidence remains unavailable. Programme/protected attributes are not credit-risk inputs.

## 6. Credit applications and offers

See [applications and offers](domain-endpoints.md#6-credit-applications-and-offers). Exact disclosures, consent, eligibility, ownership and the actual lender/product remain essential.

## 7. Repayment

See [ordinary repayment](domain-endpoints.md#7-repayment) and the [Essentials-specific contract](CURRENT_CAPABILITY_CONTRACTS.md). The ordinary loan and Essentials paths do not have identical status or idempotency-key locations. An accepted request is not confirmed collection.

## 8. Support and accessibility

See [support/accessibility](domain-endpoints.md#8-support-and-accessibility). Software preferences are not physical-device certification.

## 9. WhatsApp and USSD

See [assisted-channel callbacks](domain-endpoints.md#9-whatsapp-and-ussd). Callback authentication and replay controls are not replaced by customer-token documentation.

## 10. Admin/operations

See [admin/operations](domain-endpoints.md#10-adminoperations) and the registered route table. Role visibility in the catalogue never substitutes for target-record authorisation.

## 11. UMRA digital-lending controls

See [digital-lending operations](domain-endpoints.md#11-umra-digital-lending-controls) and the [control mapping](../../../../docs/UMRA_DIGITAL_LENDING_CONTROLS.md). Generated evidence is not proof of regulatory submission or legal certification.

## 16A. Personal financial wellbeing

See [financial wellbeing](domain-endpoints.md#16a-personal-financial-wellbeing). Recorded balances and forecasts must retain provenance and missing-data distinctions.

## 16B. Personal savings and protection

See [savings and protection](domain-endpoints.md#16b-personal-savings-and-protection). Collection, partner custody, release, payout and policy issuance remain separate states.

## 16C. Location Context and lightweight Google Maps

See [Location Context](domain-endpoints.md#16c-location-context-and-lightweight-google-maps) and [current capability contracts](CURRENT_CAPABILITY_CONTRACTS.md). Purpose-specific optional location does not authorise background tracking or location-driven credit decisions.

## 16D. Financial Space treasury, statement import and reconciliation

See [treasury and statements](domain-endpoints.md#16d-financial-space-treasury-statement-import-and-reconciliation) and [current capability contracts](CURRENT_CAPABILITY_CONTRACTS.md). The fixed opening-baseline repair is separate from full member-capital, NAV/unitisation, distributions and investment performance.

## 17. Financial Spaces and multi-entity membership

See [Financial Spaces](domain-endpoints.md#17-financial-spaces-and-multi-entity-membership). Joining a Space does not expose a person's private Personal Space.

## 18. Complete financial-life APIs

See [financial-life contracts](domain-endpoints.md#18-complete-financial-life-apis). The historical section title identifies a product area; it is not certification that every API contract or journey is complete.

## 19. Partner Catalogue, plans and subscriptions

See [catalogue, plans and subscriptions](domain-endpoints.md#19-partner-catalogue-plans-and-subscriptions). Permissions, entitlements and product eligibility are separate gates.

## 20. Revenue, service economics and financial reconciliation

See [revenue and economics](domain-endpoints.md#20-revenue-service-economics-and-financial-reconciliation). Principal, premium and capital are not platform revenue. Unknown commercial values remain unknown.

## 21. Inclusive finance, programme delivery and alternative credit support

See [inclusive finance](domain-endpoints.md#21-inclusive-finance-programme-delivery-and-alternative-credit-support) and the remaining detailed programme, impact, partner and commercial sections in [Domain endpoints](domain-endpoints.md). All original route tables and narrative examples remain in that document.

## Inclusive Impact & Outcomes

See [impact and outcomes](domain-endpoints.md#inclusive-impact--outcomes). Consent, enrolment, suppression and the non-credit measurement boundary remain mandatory.

## Essentials and other current extensions

Use [current capability contracts](CURRENT_CAPABILITY_CONTRACTS.md), the [Essentials specification](../../../../docs/product/OPFIN_ESSENTIALS.md), and the full [domain reference](domain-endpoints.md). Current unresolved financial-control reviews are not closed by this discovery interface.

## Contract coverage and maintenance

`php artisan api:catalogue --check` validates the reviewed definitions and reports missing coverage. `--require-complete` fails while any registered operation lacks its full reviewed contract. `--baseline` compares an approved previous snapshot and makes added, removed and changed operations visible.

The running catalogue updates from registered routes and checked-in guides. That is not automatic semantic completion or proof that production equals remote main. Update schemas, examples, permissions, errors, recovery and training tasks in the same change as implementation. Keep historical evidence dates intact and do not bypass the still-required financial or security acceptance work.
