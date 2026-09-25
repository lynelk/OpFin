<?php

// Reviewed contracts for the read-only developer interface. Domain operations
// remain registration-only until their complete contract is reviewed here.
$provenance = ['type' => 'object', 'required' => ['contract_fingerprint', 'runtime_fingerprint', 'environment'],
    'properties' => [
        'source_revision' => ['type' => ['string', 'null'], 'description' => 'Exact deployment revision when supplied by the platform.'],
        'runtime_fingerprint' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
        'contract_fingerprint' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
        'environment' => ['type' => 'string'], 'scope' => ['type' => 'string'],
    ]];
$operation = ['type' => 'object', 'required' => ['id', 'method', 'path', 'contract_status', 'agent_execution'],
    'properties' => ['id' => ['type' => 'string'], 'method' => ['type' => 'string'], 'path' => ['type' => 'string'],
        'summary' => ['type' => 'string'], 'description' => ['type' => 'string'], 'group' => ['type' => 'string'],
        'contract_status' => ['enum' => ['documented', 'registration_only']], 'agent_execution' => ['const' => 'not_exposed'],
        'required_role_groups' => ['type' => 'array', 'items' => ['type' => 'array', 'items' => ['type' => 'string']]],
        'parameters' => ['type' => 'array', 'items' => ['type' => 'object']],
        'request_body' => ['type' => ['object', 'null']], 'responses' => ['type' => ['object', 'array']],
        'source_digest' => ['type' => ['string', 'null']], 'guides' => ['type' => 'array', 'items' => ['type' => 'string']],
    ]];
$error = ['type' => 'object', 'required' => ['success', 'message', 'errors'],
    'properties' => ['success' => ['const' => false], 'message' => ['type' => 'string'], 'errors' => ['type' => ['object', 'array']]]];
$envelope = static fn (array $data): array => ['type' => 'object', 'required' => ['success', 'message', 'data'],
    'properties' => ['success' => ['const' => true], 'message' => ['type' => 'string'],
        'data' => ['type' => 'object', 'required' => array_keys($data), 'properties' => $data]]];
$definitions = [];
$define = static function (string $path, string $id, string $summary, string $description, bool $public, array $parameters, array $data) use (&$definitions, $envelope, $error): void {
    $definitions['GET '.$path] = [
        'status' => 'documented', 'operation_id' => $id, 'publication' => $public ? 'public' : 'authenticated',
        'group' => 'developer', 'summary' => $summary, 'description' => $description,
        'risk' => 'read_only_documentation', 'parameters' => $parameters,
        'responses' => [
            '200' => ['description' => 'The requested documentation, never an executed domain operation.',
                'headers' => ['X-Request-ID' => ['schema' => ['type' => 'string']],
                    'X-OpFin-Contract-Fingerprint' => ['schema' => ['type' => 'string']]],
                'content' => ['application/json' => ['schema' => $envelope($data)]]],
            '401' => ['description' => 'Authentication required for protected catalogue access.', 'content' => ['application/json' => ['schema' => $error]]],
            '404' => ['description' => 'No visible operation or allow-listed guide matches the identifier.', 'content' => ['application/json' => ['schema' => $error]]],
            '422' => ['description' => 'Invalid search or path input.', 'content' => ['application/json' => ['schema' => $error]]],
            '429' => ['description' => 'The documentation request rate is limited. Back off; do not retry rapidly.'],
        ],
        'guides' => ['start', 'contracts', 'agents', 'maintenance'],
    ];
};
$query = ['name' => 'q', 'in' => 'query', 'required' => false, 'description' => 'All search words must match. No query text is interpreted as code or an instruction.', 'schema' => ['type' => 'string', 'maxLength' => 160]];
$search = [$query,
    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 1]],
    ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20]],
    ['name' => 'group', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 80]],
    ['name' => 'method', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']]],
];
$results = ['items' => ['type' => 'array', 'items' => $operation], 'total' => ['type' => 'integer', 'minimum' => 0],
    'page' => ['type' => 'integer'], 'limit' => ['type' => 'integer'], 'has_more' => ['type' => 'boolean'],
    'coverage' => ['type' => 'object'], 'provenance' => $provenance];
$define('/api/developer/manifest', 'opfin_developer_manifest', 'Discover OpFin API documentation',
    'Read relative discovery links, source fingerprints and the public documentation coverage. No customer data or live provider check is returned.', true, [],
    ['name' => ['type' => 'string'], 'version' => ['type' => 'string'], 'public_catalogue' => ['type' => 'string'],
        'guides' => ['type' => 'string'], 'authenticated_catalogue' => ['type' => 'string'], 'documented_openapi' => ['type' => 'string'],
        'agent_tools' => ['type' => 'string'], 'coverage' => ['type' => 'object'], 'financial_execution' => ['const' => false],
        'safety' => ['type' => 'string'], 'provenance' => $provenance]);
$define('/api/developer/public', 'opfin_public_catalogue', 'Search public API documentation',
    'Search only explicitly published unauthenticated contracts. Passing a role or token does not expand this endpoint.', true, $search, $results);
$define('/api/developer/catalogue', 'opfin_authenticated_catalogue', 'Search the role-filtered API catalogue',
    'Requires Sanctum. Visibility derives from the authenticated role, never a query role, tenant or environment. The catalogue includes registration-only gaps but does not authorise the underlying operation.', false, $search, $results);
$define('/api/developer/operations/{operation}', 'opfin_operation_contract', 'Inspect one visible API operation',
    'Read its documented schema or explicit registration-only status, risk notes and source digest. This never dispatches the operation.', false,
    [['name' => 'operation', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'maxLength' => 96]]], ['operation' => $operation, 'provenance' => $provenance]);
$define('/api/developer/guides', 'opfin_developer_guides', 'Search learning guides',
    'Search the explicitly published learning tracks. Filesystem paths and untrusted external URLs are never accepted.', true, [$query],
    ['guides' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['id', 'title', 'audience', 'sha256']]], 'provenance' => $provenance]);
$define('/api/developer/guides/{guide}', 'opfin_developer_guide', 'Read an allow-listed learning guide',
    'Read current checked-in guide text and its content hash. Unknown identifiers receive 404; identifiers are not filesystem paths.', true,
    [['name' => 'guide', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]{0,60}$']]],
    ['guide' => ['type' => 'object', 'required' => ['id', 'title', 'audience', 'text', 'sha256']], 'provenance' => $provenance]);
$define('/api/developer/agent-tools', 'opfin_agent_discovery_tools', 'Discover read-only AI documentation tools',
    'Lists four documentation-only tool schemas. This JSON endpoint is not itself a remote MCP/OAuth transport and grants no financial execution authority.', false, [],
    ['tools' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['name', 'description', 'inputSchema', 'annotations']]],
        'execution_authority' => ['const' => 'documentation_only'], 'transport' => ['type' => 'string'], 'provenance' => $provenance]);
$define('/api/developer/openapi', 'opfin_documented_openapi', 'Export reviewed OpenAPI contracts',
    'Returns a raw OpenAPI 3.1.1 document, not the ordinary success envelope. Registration-only operations are excluded from client generation; the coverage extension reports them.', false, [], []);
$definitions['GET /api/developer/openapi']['responses']['200'] = [
    'description' => 'Role-filtered OpenAPI document containing only reviewed contracts.',
    'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['openapi', 'info', 'paths', 'x-opfin-coverage'],
        'properties' => ['openapi' => ['const' => '3.1.1'], 'info' => ['type' => 'object'], 'paths' => ['type' => 'object']]]]],
];

return $definitions;
