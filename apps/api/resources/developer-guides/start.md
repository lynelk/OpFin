# Start here: your first OpFin integration

Audience: new developers, support engineers and experienced developers new to OpFin.

## What an API request does

An API is a controlled way to ask OpFin to read information or perform a defined task. The method and path identify the task. GET normally reads a resource; POST commonly submits an instruction. Never decide that a financial request is safe solely from its method.

Authentication identifies the caller. Authorisation checks whether that caller can perform the particular task on the particular record. A valid login does not grant access to another person's financial information.

## Start without a provider account

Open `/developers` on your local or authorised API deployment. The public guides and discovery manifest need no banking, CPay, Cito or AI-model credentials. They never call a financial provider.

From an API checkout, install the declared PHP/Composer dependencies into an isolated local environment. Keep its database separate from production. Follow the repository developer guide rather than copying production environment values.

A first read-only documentation request is:

```bash
curl --fail-with-body \
  -H 'Accept: application/json' \
  http://127.0.0.1:8000/api/developer/manifest
```

Use HTTP only for an explicitly approved loopback development server. Shared environments use HTTPS.

## Find a capability

The catalogue can be searched by task, path, HTTP method or group. An authorised token enables `/api/developer/catalogue`; the server filters documentation using the authenticated role. Passing a role in the query does not grant authority.

```bash
curl --fail-with-body \
  -H 'Accept: application/json' \
  -H "Authorization: Bearer $OPFIN_TOKEN" \
  "$OPFIN_ORIGIN/api/developer/catalogue?q=repayment&limit=20"
```

`OPFIN_ORIGIN` is the approved origin without `/api`; the path above already includes it. Never include a token in the URL, source control, a screenshot or a support ticket.

## Understand the result before integrating

`documented` means the published contract supplies reviewed shape and explanatory fields. It does not mean a provider is active, a customer is eligible or the feature is financially accepted.

`registration_only` means the operation exists in the running route table, but its full contract is not yet approved for client generation. Inspect the controller, service and tests with an authorised maintainer. The machine-readable OpenAPI export excludes these entries rather than inventing payloads.

Read the operation's identifier, method/path, permissions, notes, input and response contracts. Obtain account, Space, quote and wallet identifiers from authorised results. Do not copy sample identifiers into a real financial request.

## Your next learning tracks

Read `contracts` for fields, authentication and errors; `reliability` for money and provider outages; `agents` for AI integration; `sandbox` for safe testing; and `maintenance` for keeping the contract current.
