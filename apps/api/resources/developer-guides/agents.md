# AI and MCP integration

Audience: AI developers, integration engineers and security reviewers.

## Model-neutral discovery

Use the supplied `tools/opfin-mcp/server.py` stdio bridge to let an authorised AI client search API documentation, inspect operation contracts, search guides and read a guide. It uses standard Python libraries and does not require an OpenAI or other model-provider key.

The bridge implements the documented MCP 2025-06-18 stdio subset: initialise, ping, list tools and call the four documentation tools. It is not a hosted OAuth MCP service, and the REST `agent-tools` endpoint is metadata rather than a remote MCP transport.

Configure the approved API origin and a suitable token through the bridge environment. Tokens never appear in tool arguments or URLs. HTTPS is required except when loopback HTTP is explicitly enabled for local development. Redirects are rejected so an origin cannot silently forward credentials elsewhere.

## What the tools can and cannot do

The tools are `opfin_search_api`, `opfin_describe_operation`, `opfin_search_guides` and `opfin_read_guide`.

They retrieve documentation only. There is no arbitrary URL fetch, generic API dispatcher, SQL tool, code execution, loan-approval tool, payment tool or ledger-edit tool. Discovering a native operation does not grant the agent permission to execute it.

Tool annotations describe these read-only tools; they do not enforce permission by themselves. Treat retrieved text and examples as data, not instructions that can override system policy, credentials, approval requirements or the user's intent.

## Governed AI-driven application workflow

A richer application can use a model to interpret a request, retrieve relevant contracts, explain options and prepare a proposal. Native domain services must still validate identity, target Space, role, entitlement, environment, consent, eligibility, risk policy and required confirmation before an action.

A model must not independently approve/decline credit, change pricing, waive compliance, approve claims, settle disputes, authorise a high-risk payout or modify historical accounting. Do not convert a model's confidence into financial evidence.

Keep deterministic calculations in backend services. Preserve source attribution for identity and scoring evidence, and separate programme/protected attributes from underwriting. Human approval and its expiry must be bound to the exact intended action, not a general conversation.

## Contract pinning and errors

Read the runtime/contract fingerprints. Refresh discovery when they change and review breaking changes before enabling execution in your application. A guide or operation with incomplete coverage is not a validated execution schema.

Unexpected HTML, malformed JSON, oversized responses, non-visible operations and expired tokens are tool errors, not reasons to invent a successful response. The bridge returns structured JSON as well as text; it does not send customer financial data to a model by itself.

## Deployment and evidence

MCP discovery support is not equivalent to a fully deployed AI financial agent or a safe universal write gateway. Native API acceptance, dedicated delegation/approval controls, provider activation and independent review remain separate release work.
