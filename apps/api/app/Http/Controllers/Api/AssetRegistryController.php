<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetPassport;
use App\Models\FinancialSpace;
use App\Services\AssetRegistry\AssetRegistryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetRegistryController extends Controller
{
    public function __construct(private readonly AssetRegistryService $registry) {}

    public function index(Request $request, FinancialSpace $space): JsonResponse
    {
        return ApiResponse::success('Asset passports.', ['assets' => $this->registry->list($space, $request->user())]);
    }

    public function show(Request $request, FinancialSpace $space, AssetPassport $asset): JsonResponse
    {
        return ApiResponse::success('Asset passport.', ['asset' => $this->registry->show($space, $request->user(), $asset)]);
    }

    public function register(Request $request, FinancialSpace $space): JsonResponse
    {
        $data = $request->validate([
            'asset_class' => ['required', Rule::in(array_keys(config('asset_registry.classes')))],
            'asset_subclass' => ['nullable', 'string', 'max:80'],
            'make' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:64'],
            'identifiers' => ['required', 'array', 'min:1', 'max:6'],
            'identifiers.*.type' => ['required', Rule::in(config('asset_registry.identifier_types'))],
            'identifiers.*.value' => ['required', 'string', 'max:60'],
            'supplier_profile_id' => ['nullable', 'integer', 'exists:supplier_profiles,id'],
            'purchase' => ['nullable', 'array'],
            'purchase.purchased_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'purchase.price_minor' => ['nullable', 'integer', 'min:0'],
            'purchase.currency' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'ownership_evidence_reference' => ['nullable', 'string', 'max:160'],
        ]);
        if (isset($data['purchase'])) {
            $data['purchase'] = array_intersect_key($data['purchase'], array_flip(['purchased_on', 'price_minor', 'currency']));
        }

        return ApiResponse::success('Asset registered.', ['asset' => $this->registry->register($space, $request->user(), $data, $this->key($request))], 201);
    }

    public function reportStolen(Request $request, FinancialSpace $space, AssetPassport $asset): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:240'],
            'police_reference' => ['nullable', 'string', 'max:80'],
            'reported_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        return ApiResponse::success('Theft report recorded.', ['asset' => $this->registry->reportStolen($space, $request->user(), $asset, $data, $this->key($request))]);
    }

    public function dispose(Request $request, FinancialSpace $space, AssetPassport $asset): JsonResponse
    {
        $data = $request->validate([
            'disposal' => ['required', Rule::in(['sold', 'traded_in', 'scrapped', 'transferred', 'written_off'])],
            'reason' => ['required', 'string', 'max:240'],
        ]);

        return ApiResponse::success('Asset disposal recorded.', ['asset' => $this->registry->dispose($space, $request->user(), $asset, $data, $this->key($request))]);
    }

    public function verify(Request $request, AssetPassport $asset): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(config('asset_registry.verification_methods'))],
            'evidence_reference' => ['required', 'string', 'max:160'],
        ]);

        return ApiResponse::success('Asset verified.', ['asset' => $this->registry->verify($request->user(), $asset, $data, $this->key($request))]);
    }

    public function review(Request $request, AssetPassport $asset): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['activate', 'reject'])],
            'reason' => ['required', 'string', 'max:240'],
        ]);

        return ApiResponse::success('Asset review recorded.', ['asset' => $this->registry->resolveReview($request->user(), $asset, $data, $this->key($request))]);
    }

    public function reviewQueue(): JsonResponse
    {
        return ApiResponse::success('Assets needing review.', ['assets' => $this->registry->reviewQueue()]);
    }

    public function encumber(Request $request, AssetPassport $asset): JsonResponse
    {
        $data = $request->validate(['financing_arrangement_id' => ['required', 'integer', 'min:1']]);

        return ApiResponse::success('Lien registered.', ['asset' => $this->registry->encumber($request->user(), $asset, $data, $this->key($request))], 201);
    }

    public function release(Request $request, int $encumbrance): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);

        return ApiResponse::success('Lien released.', ['asset' => $this->registry->release($request->user(), $encumbrance, $data, $this->key($request))]);
    }

    public function recover(Request $request, AssetPassport $asset): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);

        return ApiResponse::success('Recovery recorded.', ['asset' => $this->registry->recover($request->user(), $asset, $data, $this->key($request))]);
    }

    public function identifierCheck(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(config('asset_registry.identifier_types'))],
            'value' => ['required', 'string', 'max:60'],
        ]);

        return ApiResponse::success('Registry state.', $this->registry->identifierCheck($request->user(), $data['type'], $data['value']));
    }

    private function key(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        abort_if($key === '' || strlen($key) > 160, 422, 'An Idempotency-Key header (up to 160 characters) is required.');

        return $key;
    }
}
