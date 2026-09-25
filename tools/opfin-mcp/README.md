# OpFin documentation MCP bridge

This local Python 3.10+ stdio bridge lets an authorised AI client search and read OpFin API documentation. It has no third-party Python dependencies and no model-provider dependency.

Set `OPFIN_API_ORIGIN` to the approved HTTPS origin without `/api`, and supply a suitable token as `OPFIN_API_TOKEN` through the host's secret/environment configuration. Run `python3 tools/opfin-mcp/server.py`. For a local development server only, `OPFIN_ALLOW_LOOPBACK_HTTP=true` permits HTTP on localhost, 127.0.0.1 or ::1.

Do not put credentials in command-line arguments, tool inputs, source files or a shared MCP configuration committed to Git. The bridge rejects redirects and refuses arbitrary API paths.

It implements MCP protocol version 2025-06-18 for initialise, ping, tools/list and tools/call over newline-delimited stdio JSON. The supported tools are API search, operation description, guide search and guide read. The remote metadata must match those four read-only tools; unexpected write tools are rejected.

This is not a remote MCP OAuth server, an AI model, a payment agent or a generic HTTP tool. Native API actions remain outside its scope. Retrieved text must not be promoted into authority to override user consent or financial controls.

Run `python3 -m unittest discover -s tools/opfin-mcp -p 'test_*.py' -v` for its local safety tests. See the Developer Centre's `agents`, `sandbox` and `maintenance` tracks for the wider integration contract.
