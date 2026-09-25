# Contracts, fields, authentication and errors

Audience: all developers.

## Stable discovery contracts

The developer interface supplies a manifest, paginated catalogue, operation details, guide search, guide text, reviewed OpenAPI and documentation-tool metadata. Its routes are under `/api/developer`.

Public discovery is explicitly allow-listed. The full catalogue, operation details, OpenAPI and tool metadata require Sanctum authentication. Documentation visibility is not domain authorisation: the native operation must still enforce ownership, Financial Space authority, roles, entitlement, consent and policy.

Query fields are `q`, `page`, `limit`, `group` and `method`. The defaults are page 1 and limit 20, with limit capped at 50. Query length is capped at 160 characters. All query words must match. Search never evaluates SQL, code, model instructions or external URLs.

The server issues `X-Request-ID` and `X-OpFin-Contract-Fingerprint` on successful discovery JSON responses. They are not universal promises for every legacy endpoint. Protected discovery responses are private and must not be cached across users.

## Response shape

Ordinary developer responses contain `success`, `message` and `data`. Errors contain `success`, `message` and `errors`. The raw OpenAPI endpoint deliberately returns an OpenAPI document without that envelope. Other domain HTML/CSV exports, proxies and framework failures must be handled according to their own contracts.

401 requires valid authentication. A 404 can mean an unknown or non-visible resource; do not probe another user's identifiers. 422 identifies invalid input. 429 requires backoff. A 5xx or network timeout is not proof that a financial request failed before execution.

Do not assume every endpoint uses the same status convention. In the current Essentials contract, a created repayment record returns 201, while ordinary loan repayment initiation can return 202. Neither proves collected money.

## Schemas and compatibility

The reviewed export uses OpenAPI 3.1.1. Each operation has a stable identifier, parameters, responses, security metadata and OpFin coverage/risk extensions. Version 1.0.0 of the discovery interface does not invent `/api/v1` routes for the rest of the platform.

Only explicitly reviewed contracts appear in the SDK-oriented export. Coverage reports include omitted operations. A valid OpenAPI file can still have incomplete business coverage; read both the document and its coverage extension.

## Money, credentials and identifiers

Keep monetary amounts in integer minor units under the configured currency contract. UGX examples use exponent zero; do not assume every currency does. Required versus optional fields are endpoint-specific.

Use least-privilege, environment-specific tokens. The platform's standard sign-in journey and an agent's delegated authority are different concerns. Do not hand a general administrator credential to an AI merely to make an integration easier.

Never return raw NINs, biometric evidence, provider keys or private financial records in developer examples. Source fingerprints are hashes of source files, not data exports.

## Evidence boundaries

The manifest identifies the running source revision when available and always supplies deterministic runtime/contract fingerprints. It does not query GitHub to claim that production equals remote main. Compare the actual release evidence when deciding which version is deployed.
