# Safe test environments and acceptance

Audience: all developers and testers.

## Do not use production as a playground

Use an isolated local or authorised sandbox database, synthetic people and financial records, and environment-specific credentials. A `sandbox=true` request field is not isolation. The server and provider routes must enforce the intended environment.

Do not copy production NINs, biometric documents, tokens, passwords or private account details into fixtures, model prompts or demonstrations. Mock/provider-simulator outcomes must remain visibly non-live.

## A first acceptance sequence

Read the public manifest and a guide without provider credentials. Sign in through the approved test process. Search the authenticated catalogue. Confirm a customer cannot discover privileged administrative contracts and an unauthorised identifier returns no data. Export reviewed OpenAPI and inspect its coverage gaps.

For a domain integration, use the actual reviewed endpoint contract, not guesses from route names. Test success, invalid fields, missing authentication, wrong role/Space, withdrawn consent, duplicate/replayed instructions and provider failures. Money requires expected accounting and reconciliation assertions, not only HTTP status checks.

## AI integration tests

Initialise the local bridge and list its four tools. Search and read a guide. Verify unknown tools, extra arguments, arbitrary URLs, oversized messages and financial-execution requests are rejected. Disconnect the API and confirm an explicit error rather than fabricated guidance.

No developer documentation endpoint invokes CPay, Cito or a model provider. These discovery tests therefore do not need live provider activation.

## Honest release evidence

Record exact source revision, environment, command, test result, reviewer and operating evidence. A source merge, a passing SQLite test, a successful Web build and a production financial acceptance are different states.

This change does not provision a new hosted sandbox, database or Railway service. GitHub Actions remains deferred; use the existing approved verification path without disabling tests or audits. PostgreSQL concurrency, provider certification and real-device acceptance must not be inferred from documentation-tool tests.
