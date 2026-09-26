# OpFin API and developer entry point

Reviewed: 25 September 2026. Language: English (United Kingdom).

The source-linked Developer Centre is `/developers` on the API origin when this change is deployed. It serves novice, application-developer and advanced/AI learning tracks, searchable route discovery, reviewed OpenAPI and explicit contract-coverage gaps.

Start with [Developer interface](apps/api/docs/api/DEVELOPER_INTERFACE.md), [API and AI platform](docs/developer/API_AND_AGENT_PLATFORM.md), [existing domain contracts](apps/api/docs/api/current-endpoints.md), and [the local MCP bridge](tools/opfin-mcp/README.md).

```bash
make api-docs
make api-docs-check
make agent-docs-test
cd apps/api && php artisan api:catalogue --check --require-complete
```

The strict completion command must fail while any registered operation still lacks a reviewed contract. Do not confuse complete route discovery with a complete business/API specification or a financially accepted release.

The bridge is read-only documentation discovery. It cannot execute arbitrary native endpoints, approve credit, move funds or alter ledger entries. A richer AI application must use the existing domain controls, least-privilege delegation, consent, approval, idempotency and financial reconciliation.

Runtime fingerprints identify the running source and guide content; they do not assume remote main equals production. No new hosted service or GitHub Actions workflow is required. Existing financial-control and credential-security findings remain separate acceptance work.
