# OpFin API and developer entry point

**Classification:** Controlled external reference  
**Reviewed:** 21 October 2026  
**Language:** English (United Kingdom)

OpFin is a server-authoritative financial platform. The API supplies identity, Financial Space authority, financial records and controlled product workflows to Web, mobile and authorised integrations. The Developer Centre is searchable, but **route discovery is not permission to act**.

## First steps by experience

| Audience | Read this | First safe action |
| --- | --- | --- |
| New to APIs | [Plain-language integrator guide](apps/api/docs/api/INTEGRATOR_GUIDE.md) | Query the public developer manifest and guides |
| App/backend developer | [Developer start](docs/DEVELOPER_START_HERE.md), [current routes](apps/api/docs/api/current-endpoints.md) | Inspect a reviewed operation and its actual role/Space requirements |
| Senior/enterprise integrator | [Domain API contracts](apps/api/docs/README.md), [financial governance](docs/FINANCIAL_CHANGE_GOVERNANCE.md) | Validate negative authorisation, idempotency, provider uncertainty and accounting |
| AI/agent integrator | [Developer interface](apps/api/docs/api/DEVELOPER_INTERFACE.md), [API/AI platform](docs/developer/API_AND_AGENT_PLATFORM.md) | Use documentation-only discovery, never an invented financial write |

## Locate the source-linked interface

- `GET /api/developer/manifest`: public capability and coverage overview.
- `GET /api/developer/public` and `GET /api/developer/guides`: public search/learning.
- `GET /api/developer/catalogue`, `/operations/{operation}`, `/openapi`, `/agent-tools`: authenticated, role-filtered discovery.
- `/developers`: browser interface on the API origin when deployed and enabled.
- [Local documentation-only MCP bridge](tools/opfin-mcp/README.md): search/read descriptions only, with no financial execution.

A registered endpoint can be `registration_only` rather than fully reviewed; only reviewed operations belong in the generated OpenAPI export. Successful HTTP delivery does not establish financial settlement. Exported HTML/CSV and framework errors may use different envelopes from normal JSON responses.

## Safe first request

Set `OPFIN_API_ORIGIN` to the **approved** origin without a trailing `/api`. No token is necessary to read the public manifest:

```bash
curl --fail-with-body --silent --show-error \
  --header 'Accept: application/json' \
  "$OPFIN_API_ORIGIN/api/developer/manifest"
```

Do not paste real API tokens into documentation, AI prompts or public issue logs. To make an authenticated request, obtain an authorised test token via the actual login/consent flow and follow the [integrator guide](apps/api/docs/api/INTEGRATOR_GUIDE.md).

## Keep contracts current

```bash
make docs-check
make publication-check
make api-docs
make api-docs-check
make agent-docs-test
cd apps/api && php artisan api:catalogue --check --require-complete
```

The strict completeness gate must fail for missing reviewed schemas. Do not invent schemas from path names or replace a real control failure with a passing documentation-only test. Update request/response fields, roles, errors, idempotency and recovery, UAT/training and accounting evidence alongside the implementation. See [current status](docs/CURRENT_STATE.md) and [source-linked review](docs/operations/DOCUMENTATION_ALIGNMENT_2026-10-21.md).
