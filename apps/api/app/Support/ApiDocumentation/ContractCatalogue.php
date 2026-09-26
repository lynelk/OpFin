<?php

namespace App\Support\ApiDocumentation;

use InvalidArgumentException;

/** Pure, side-effect-free contract discovery. It never dispatches a domain operation. */
final class ContractCatalogue
{
    private array $operations = [];

    private array $issues = [];

    public function __construct(array $routes, private readonly array $contracts, private readonly array $guides, private readonly array $provenance)
    {
        $seen = [];
        foreach ($routes as $route) {
            $method = strtoupper((string) ($route['method'] ?? ''));
            $path = '/'.ltrim((string) ($route['path'] ?? ''), '/');
            if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], true)
                || ! str_starts_with($path, '/api/') || str_starts_with($path, '/api/demo/')) {
                continue;
            }
            $key = $method.' '.$path;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicate API operation in catalogue: '.$key);
            }
            $seen[$key] = true;
            $contract = $contracts[$key] ?? [];
            $middleware = array_values(array_filter($route['middleware'] ?? [], 'is_string'));
            $roles = [];
            foreach ($middleware as $item) {
                if (str_starts_with($item, 'role:') || str_contains($item, 'EnsureUserHasRole:')) {
                    $roles[] = array_values(array_filter(explode(',', substr($item, strpos($item, ':') + 1))));
                }
            }
            $authenticated = in_array('auth:sanctum', $middleware, true);
            $documented = ($contract['status'] ?? '') === 'documented';
            if ($documented && (empty($contract['summary']) || empty($contract['description']) || empty($contract['responses']))) {
                throw new InvalidArgumentException('A documented contract is incomplete: '.$key);
            }
            // A role-restricted route cannot be published anonymously by a contract typo.
            $public = ($contract['publication'] ?? '') === 'public' && ! $authenticated && $roles === [];
            $operation = [
                'id' => $contract['operation_id'] ?? 'op_'.strtolower($method).'_'.substr(hash('sha256', $path), 0, 16),
                'method' => $method,
                'path' => $path,
                'group' => $contract['group'] ?? $this->group($path),
                'summary' => $contract['summary'] ?? $method.' '.$path,
                'description' => $contract['description'] ?? 'Registered in this source build. Request, response and business rules still require a reviewed contract. Do not generate an executing agent from registration alone.',
                'contract_status' => $documented ? 'documented' : 'registration_only',
                'availability' => 'registration_is_not_provider_activation_or_release_acceptance',
                'risk' => $contract['risk'] ?? 'unreviewed',
                'authentication' => $authenticated ? 'sanctum' : 'endpoint_specific',
                'required_role_groups' => $roles,
                'public_documentation' => $public,
                'agent_execution' => 'not_exposed',
                'source_digest' => $route['source_digest'] ?? null,
                'parameters' => $contract['parameters'] ?? $this->pathParameters($path),
                'request_body' => $contract['requestBody'] ?? null,
                'responses' => $contract['responses'] ?? [],
                'guides' => $contract['guides'] ?? ['start', 'reliability', 'agents'],
                'notes' => $contract['notes'] ?? [],
                'source_key' => $key,
            ];
            if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,95}$/D', $operation['id'])) {
                throw new InvalidArgumentException('Invalid stable operation identifier: '.$key);
            }
            $this->operations[] = $operation;
        }
        if ($this->operations === []) {
            throw new InvalidArgumentException('No registered non-demo API operations were supplied.');
        }
        usort($this->operations, static fn (array $a, array $b): int => strcmp($a['source_key'], $b['source_key']));
        if (count(array_unique(array_column($this->operations, 'id'))) !== count($this->operations)) {
            throw new InvalidArgumentException('Operation identifiers must be unique.');
        }
        foreach (array_keys($contracts) as $key) {
            if (! isset($seen[$key])) {
                $this->issues[] = ['code' => 'contract_without_route', 'operation' => $key];
            }
        }
    }

    /** Role groups are ANDed, matching multiple Laravel role middleware requirements. */
    public function visible(?string $role): array
    {
        return array_values(array_filter($this->operations, static function (array $operation) use ($role): bool {
            if ($role === null) {
                return $operation['public_documentation'];
            }
            if ($operation['required_role_groups'] !== []) {
                foreach ($operation['required_role_groups'] as $roles) {
                    if (! in_array($role, $roles, true)) {
                        return false;
                    }
                }
            }
            // Never infer access to unclassified administrative/provider routes.
            if (preg_match('#^/api/(admin|internal|webhooks|ussd)(/|$)#', $operation['path'])
                && $operation['required_role_groups'] === []
                && ! in_array($role, ['platform_admin', 'operations'], true)) {
                return false;
            }
            if (str_starts_with($operation['path'], '/api/partner/') && $operation['required_role_groups'] === []) {
                return false;
            }

            return $operation['authentication'] === 'sanctum' || $operation['public_documentation']
                || in_array($role, ['platform_admin', 'operations'], true);
        }));
    }

    public function search(?string $role, string $query = '', int $page = 1, int $limit = 20, ?string $group = null, ?string $method = null): array
    {
        if ((preg_match_all('/./us', $query) ?: 0) > 160 || $page < 1 || $page > 1000 || $limit < 1 || $limit > 50) {
            throw new InvalidArgumentException('Search limits are query 160 characters, page 1–1000 and page size 1–50.');
        }
        $words = array_slice(preg_split('/\s+/', strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 12);
        $results = [];
        foreach ($this->visible($role) as $operation) {
            if (($group !== null && $operation['group'] !== $group) || ($method !== null && $operation['method'] !== strtoupper($method))) {
                continue;
            }
            $text = strtolower(implode(' ', [$operation['id'], $operation['method'], $operation['path'], $operation['group'], $operation['summary'], $operation['description']]));
            foreach ($words as $word) {
                if (! str_contains($text, $word)) {
                    continue 2;
                }
            }
            $score = 0;
            foreach ($words as $word) {
                $score += (str_contains(strtolower($operation['summary']), $word) ? 4 : 0)
                    + (str_contains(strtolower($operation['path']), $word) ? 2 : 0);
            }
            $operation['relevance'] = $score;
            $results[] = $operation;
        }
        usort($results, static fn (array $a, array $b): int => ($b['relevance'] <=> $a['relevance']) ?: strcmp($a['source_key'], $b['source_key']));

        return ['items' => array_slice($results, ($page - 1) * $limit, $limit), 'total' => count($results),
            'page' => $page, 'limit' => $limit, 'has_more' => $page * $limit < count($results), 'provenance' => $this->provenance];
    }

    public function operation(?string $role, string $id): ?array
    {
        foreach ($this->visible($role) as $operation) {
            if (hash_equals($operation['id'], $id)) {
                return $operation;
            }
        }

        return null;
    }

    public function guide(string $id): ?array
    {
        // IDs are resolved in an explicit allow-list. They are never file paths.
        return $this->guides[$id] ?? null;
    }

    public function guides(string $query = ''): array
    {
        if ((preg_match_all('/./us', $query) ?: 0) > 160) {
            throw new InvalidArgumentException('Guide query is too long.');
        }
        $words = preg_split('/\s+/', strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $results = [];
        foreach ($this->guides as $id => $guide) {
            $haystack = strtolower($guide['title'].' '.$guide['text']);
            foreach ($words as $word) {
                if (! str_contains($haystack, $word)) {
                    continue 2;
                }
            }
            $results[] = ['id' => $id, 'title' => $guide['title'], 'audience' => $guide['audience'], 'sha256' => $guide['sha256']];
        }

        return $results;
    }

    public function coverage(?string $role = null, bool $internal = false): array
    {
        $operations = $internal ? $this->operations : $this->visible($role);
        $missing = array_values(array_filter($operations, static fn (array $row): bool => $row['contract_status'] !== 'documented'));

        return ['registered' => count($operations), 'documented' => count($operations) - count($missing),
            'registration_only' => count($missing), 'complete' => $missing === [] && $this->issues === [],
            'unreviewed_operations' => array_column($missing, 'source_key'),
            'definition_errors' => $internal ? $this->issues : [], 'provenance' => $this->provenance];
    }

    /** Reviewed operations only; internal inventory bypasses role filtering, never schema review. */
    public function openApi(?string $role = null, bool $internalInventory = false): array
    {
        $paths = [];
        foreach ($internalInventory ? $this->operations : $this->visible($role) as $operation) {
            if ($operation['contract_status'] !== 'documented') {
                continue;
            }
            $item = [
                'operationId' => $operation['id'], 'summary' => $operation['summary'], 'description' => $operation['description'],
                'tags' => [$operation['group']], 'parameters' => $operation['parameters'],
                'responses' => $operation['responses'] ?: ['default' => ['description' => 'Response contract has not been reviewed. Do not generate an executing client from this entry.']],
                'x-opfin-contract-status' => $operation['contract_status'],
                'x-opfin-agent-execution' => 'not_exposed',
                'x-opfin-risk' => $operation['risk'],
                'x-opfin-required-role-groups' => $operation['required_role_groups'],
                'x-opfin-source-digest' => $operation['source_digest'],
                'x-opfin-availability' => $operation['availability'],
            ];
            if ($operation['authentication'] === 'sanctum') {
                $item['security'] = [['sanctumBearer' => []]];
            } elseif ($operation['public_documentation']) {
                $item['security'] = [];
            } else {
                throw new InvalidArgumentException('A documented non-Sanctum operation needs explicit reviewed authentication before OpenAPI publication.');
            }
            if ($operation['request_body'] !== null) {
                $item['requestBody'] = $operation['request_body'];
            }
            $paths[$operation['path']][strtolower($operation['method'])] = $item;
        }
        ksort($paths, SORT_STRING);

        return ['openapi' => '3.1.1', 'info' => ['title' => 'OpFin API', 'version' => '1.0.0',
            'description' => 'Source-linked reviewed contracts. Registration, documented shape, authorisation, provider activation and release acceptance are separate. Unreviewed operations are excluded from both HTTP and offline OpenAPI exports.'],
            'servers' => [['url' => '/', 'description' => 'Same deployment origin. Paths already include /api.']],
            'paths' => $paths === [] ? new \stdClass : $paths,
            'components' => ['securitySchemes' => [
                'sanctumBearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'An authorised Sanctum token. A token does not bypass record ownership, roles, entitlements or consent.'],
            ]],
            'x-opfin-provenance' => $this->provenance,
            'x-opfin-coverage' => $this->coverage($role, $internalInventory),
            'x-opfin-intended-use' => 'documented_contracts_only'];
    }

    public function snapshot(): array
    {
        return ['format_version' => 1, 'provenance' => $this->provenance,
            'operations' => array_map(static fn (array $op): array => [
                'key' => $op['source_key'], 'id' => $op['id'], 'source_digest' => $op['source_digest'],
                'contract_digest' => hash('sha256', json_encode([$op['description'], $op['parameters'], $op['request_body'], $op['responses'], $op['notes']], JSON_THROW_ON_ERROR)),
                'contract_status' => $op['contract_status'],
            ], $this->operations)];
    }

    public function diff(array $baseline): array
    {
        if (($baseline['format_version'] ?? null) !== 1 || ! is_array($baseline['operations'] ?? null)) {
            throw new InvalidArgumentException('A versioned OpFin catalogue snapshot is required.');
        }
        $before = [];
        foreach ($baseline['operations'] as $entry) {
            if (! is_array($entry) || ! is_string($entry['key'] ?? null) || ! is_string($entry['id'] ?? null)
                || ! array_key_exists('source_digest', $entry) || ! is_string($entry['contract_digest'] ?? null)
                || ! in_array($entry['contract_status'] ?? null, ['documented', 'registration_only'], true)
                || isset($before[$entry['key']])) {
                throw new InvalidArgumentException('The baseline contains malformed or duplicate operation entries.');
            }
            $before[$entry['key']] = $entry;
        }
        $after = array_column($this->snapshot()['operations'], null, 'key');
        $changed = [];
        foreach ($after as $key => $row) {
            if (! isset($before[$key])) {
                $changed[] = ['kind' => 'added', 'operation' => $key, 'review_required' => $row['contract_status'] !== 'documented'];
                continue;
            }
            $previous = $before[$key];
            $identityChanged = $row['id'] !== $previous['id'];
            $statusChanged = $row['contract_status'] !== $previous['contract_status'];
            $sourceChanged = $row['source_digest'] !== $previous['source_digest'];
            $contractChanged = $row['contract_digest'] !== $previous['contract_digest'];
            if ($identityChanged || $statusChanged || $sourceChanged || $contractChanged) {
                $changed[] = ['kind' => 'changed', 'operation' => $key,
                    'previous_operation_id' => $previous['id'], 'operation_id' => $row['id'],
                    'identity_changed' => $identityChanged, 'contract_status_changed' => $statusChanged,
                    'review_required' => $identityChanged
                        || ($statusChanged && $row['contract_status'] !== 'documented')
                        || ($sourceChanged && ! $contractChanged)];
            }
        }
        foreach (array_diff_key($before, $after) as $key => $_) {
            $changed[] = ['kind' => 'removed', 'operation' => $key, 'review_required' => true];
        }

        return $changed;
    }

    private function group(string $path): string
    {
        $parts = explode('/', trim($path, '/'));

        return in_array($parts[1] ?? '', ['admin', 'partner'], true) ? ($parts[1].'/'.($parts[2] ?? 'general')) : ($parts[1] ?? 'general');
    }

    private function pathParameters(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return array_map(static fn (string $name): array => ['name' => rtrim($name, '?'), 'in' => 'path', 'required' => true,
            'description' => 'Use the authorised identifier supplied by the API. Registration does not establish identifier type or ownership.',
            'schema' => ['type' => 'string']], $matches[1]);
    }
}
