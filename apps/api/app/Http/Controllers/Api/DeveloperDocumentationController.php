<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiDocumentation\ApiDiscovery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeveloperDocumentationController extends Controller
{
    public function __construct(private readonly ApiDiscovery $discovery) {}

    public function manifest(): JsonResponse
    {
        return $this->reply('OpFin developer discovery.', [
            'name' => 'OpFin developer and agent discovery', 'version' => '1.0.0',
            'public_catalogue' => '/api/developer/public', 'guides' => '/api/developer/guides',
            'authenticated_catalogue' => '/api/developer/catalogue',
            'documented_openapi' => '/api/developer/openapi', 'agent_tools' => '/api/developer/agent-tools',
            'coverage' => $this->discovery->catalogue()->coverage(),
            'financial_execution' => false,
            'safety' => 'Discovery cannot approve credit, change pricing, move funds, bypass consent or alter accounting. Native domain APIs retain their independent controls.',
        ]);
    }

    public function publicCatalogue(Request $request): JsonResponse
    {
        return $this->search($request, null);
    }

    public function catalogue(Request $request): JsonResponse
    {
        return $this->search($request, (string) $request->user()->role);
    }

    public function operation(Request $request, string $operation): JsonResponse
    {
        $result = $this->discovery->catalogue()->operation((string) $request->user()->role, $operation);
        abort_if($result === null, 404);

        return $this->reply('API operation contract.', ['operation' => $result]);
    }

    public function guides(Request $request): JsonResponse
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:160']]);

        return $this->reply('Developer guides.', ['guides' => $this->discovery->catalogue()->guides((string) ($input['q'] ?? ''))]);
    }

    public function guide(string $guide): JsonResponse
    {
        $result = $this->discovery->catalogue()->guide($guide);
        abort_if($result === null, 404);

        return $this->reply('Developer guide.', ['guide' => $result]);
    }

    public function openapi(Request $request): JsonResponse
    {
        // Deliberately exclude registration-only entries from an executing SDK input.
        return $this->headers(response()->json($this->discovery->catalogue()->openApi((string) $request->user()->role)));
    }

    public function agentTools(): JsonResponse
    {
        $object = ['type' => 'object', 'additionalProperties' => false];
        $tools = [
            ['name' => 'opfin_search_api', 'description' => 'Search visible API documentation. Does not call or execute the discovered operation.',
                'inputSchema' => array_merge($object, ['properties' => ['query' => ['type' => 'string', 'maxLength' => 160], 'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000]], 'required' => ['query']])],
            ['name' => 'opfin_describe_operation', 'description' => 'Read one visible operation contract and its readiness limitations. Does not execute it.',
                'inputSchema' => array_merge($object, ['properties' => ['operation_id' => ['type' => 'string', 'maxLength' => 96]], 'required' => ['operation_id']])],
            ['name' => 'opfin_search_guides', 'description' => 'Search curated OpFin developer and AI-integration guides.',
                'inputSchema' => array_merge($object, ['properties' => ['query' => ['type' => 'string', 'maxLength' => 160]], 'required' => ['query']])],
            ['name' => 'opfin_read_guide', 'description' => 'Read an allow-listed developer guide. Guide identifiers are not file paths.',
                'inputSchema' => array_merge($object, ['properties' => ['guide_id' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]{0,60}$']], 'required' => ['guide_id']])],
        ];
        foreach ($tools as &$tool) {
            $tool['annotations'] = ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false];
        }
        unset($tool);

        return $this->reply('Read-only agent discovery tools.', ['tools' => $tools, 'execution_authority' => 'documentation_only',
            'transport' => 'Use the supplied local stdio bridge; this endpoint is metadata, not a remote MCP/OAuth server.']);
    }

    public function portal()
    {
        return response()->view('developer.portal')->withHeaders([
            'Content-Security-Policy' => "default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store',
        ]);
    }

    private function search(Request $request, ?string $role): JsonResponse
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:160'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'], 'group' => ['nullable', 'string', 'max:80'],
            'method' => ['nullable', 'in:GET,POST,PUT,PATCH,DELETE,OPTIONS'],
        ]);
        // No caller-supplied role, environment, tenant or provider setting is consumed.
        $result = $this->discovery->catalogue()->search($role, (string) ($input['q'] ?? ''),
            (int) ($input['page'] ?? 1), (int) ($input['limit'] ?? 20), $input['group'] ?? null, $input['method'] ?? null);
        $result['coverage'] = $this->discovery->catalogue()->coverage($role);

        return $this->reply('Visible API documentation.', $result);
    }

    private function reply(string $message, array $data): JsonResponse
    {
        $data['provenance'] ??= $this->discovery->catalogue()->coverage()['provenance'];

        return $this->headers(ApiResponse::success($message, $data));
    }

    private function headers(JsonResponse $response): JsonResponse
    {
        return $response->withHeaders([
            'Cache-Control' => 'private, no-store', 'Vary' => 'Authorization, Cookie',
            'X-Content-Type-Options' => 'nosniff', 'X-Request-ID' => (string) Str::uuid(),
            'X-OpFin-Contract-Fingerprint' => $this->discovery->catalogue()->coverage()['provenance']['contract_fingerprint'],
        ]);
    }
}
