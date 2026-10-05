<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialProduct;
use App\Models\LegalProductPassport;
use App\Models\ProductTemplate;
use App\Services\ProductFactory\ProductFactoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductFactoryController extends Controller
{
    public function __construct(private readonly ProductFactoryService $factory) {}

    public function templates(): JsonResponse
    {
        return ApiResponse::success('Product templates.', ['templates' => ProductTemplate::query()->orderBy('code')->orderByDesc('version')->limit(200)->get()]);
    }

    public function createTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'regex:/^[A-Z0-9_]{3,40}$/'],
            'name' => ['required', 'string', 'max:180'],
            'family' => ['required', Rule::in(config('product_factory.families'))],
            'rail' => ['required', Rule::in(array_keys(config('product_factory.rails')))],
            'contract_types' => ['required', 'array', 'min:1'],
            'contract_types.*' => ['string', 'max:40'],
            'guardrails' => ['required', 'array'],
            'policy_reference' => ['required', 'string', 'max:200'],
        ]);

        return ApiResponse::success('Template drafted.', ['template' => $this->factory->createTemplate($request->user(), $data, $this->key($request))], 201);
    }

    public function approveTemplate(Request $request, ProductTemplate $template): JsonResponse
    {
        return ApiResponse::success('Template approved.', ['template' => $this->factory->approveTemplate($request->user(), $template)]);
    }

    public function retireTemplate(Request $request, ProductTemplate $template): JsonResponse
    {
        return ApiResponse::success('Template retired.', ['template' => $this->factory->retireTemplate($request->user(), $template)]);
    }

    public function passports(): JsonResponse
    {
        return ApiResponse::success('Legal Product Passports.', ['passports' => LegalProductPassport::query()->orderByDesc('id')->limit(200)->get()]);
    }

    public function createPassport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jurisdiction' => ['required', Rule::in(['UG'])],
            'regulated_activity' => ['required', 'string', 'max:120'],
            'booking_entity' => ['required', 'string', 'max:160'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'funding_entity' => ['nullable', 'string', 'max:160'],
            'servicer' => ['nullable', 'string', 'max:160'],
            'restrictions' => ['nullable', 'array'],
            'tax_accounting_references' => ['nullable', 'array'],
        ]);

        return ApiResponse::success('Passport drafted.', ['passport' => $this->factory->createPassport($request->user(), $data, $this->key($request))], 201);
    }

    public function approvePassport(Request $request, LegalProductPassport $passport): JsonResponse
    {
        $data = $request->validate([
            'licence_or_approval_reference' => ['required', 'string', 'max:200'],
            'evidence_reference' => ['required', 'string', 'max:200'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);

        return ApiResponse::success('Passport approved.', ['passport' => $this->factory->approvePassport($request->user(), $passport, $data)]);
    }

    public function revokePassport(Request $request, LegalProductPassport $passport): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);

        return ApiResponse::success('Passport revoked.', ['passport' => $this->factory->revokePassport($request->user(), $passport, $data['reason'])]);
    }

    public function products(): JsonResponse
    {
        return ApiResponse::success('Factory products.', ['products' => FinancialProduct::query()->whereNotNull('product_template_id')
            ->orderBy('code')->orderByDesc('version')->limit(200)->get()]);
    }

    public function createProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'regex:/^[A-Z0-9_]{3,40}$/'],
            'product_template_id' => ['required', 'integer'],
            ...$this->productRules(true),
        ]);

        return ApiResponse::success('Product drafted.', ['product' => $this->factory->createProduct($request->user(), $data, $this->key($request))], 201);
    }

    public function updateProduct(Request $request, FinancialProduct $product): JsonResponse
    {
        return ApiResponse::success('Draft updated.', ['product' => $this->factory->updateDraft($request->user(), $product, $request->validate($this->productRules(false)))]);
    }

    public function submitProduct(Request $request, FinancialProduct $product): JsonResponse
    {
        return ApiResponse::success('Product submitted for approval.', ['product' => $this->factory->submit($request->user(), $product)]);
    }

    public function approveProduct(Request $request, FinancialProduct $product): JsonResponse
    {
        return ApiResponse::success('Product approved.', ['product' => $this->factory->approve($request->user(), $product)]);
    }

    public function rejectProduct(Request $request, FinancialProduct $product): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:240']]);

        return ApiResponse::success('Product returned to draft.', ['product' => $this->factory->reject($request->user(), $product, $data['reason'])]);
    }

    public function activateProduct(Request $request, FinancialProduct $product): JsonResponse
    {
        return ApiResponse::success('Product is live.', ['product' => $this->factory->activate($request->user(), $product)]);
    }

    public function retireProduct(Request $request, FinancialProduct $product): JsonResponse
    {
        return ApiResponse::success('Product retired.', ['product' => $this->factory->retire($request->user(), $product)]);
    }

    public function reviseProduct(Request $request, FinancialProduct $product): JsonResponse
    {
        $data = $request->validate(['product_template_id' => ['sometimes', 'integer'], ...$this->productRules(false)]);

        return ApiResponse::success('New draft version created.', ['product' => $this->factory->revise($request->user(), $product, $data, $this->key($request))], 201);
    }

    private function productRules(bool $required): array
    {
        $need = $required ? 'required' : 'sometimes';

        return [
            'name' => [$need, 'string', 'max:180'],
            'contract_type' => [$need, 'string', 'max:40'],
            'currency' => ['sometimes', 'regex:/^[A-Z]{3}$/'],
            'partner_id' => ['sometimes', 'nullable', 'integer', 'exists:partners,id'],
            'legal_product_passport_id' => [$need, 'integer'],
            'sharia_approval_id' => ['sometimes', 'nullable', 'integer'],
            'customer_classes' => ['sometimes', 'nullable', 'array'],
            'parameters' => [$need, 'array'],
            'disclosure' => [$need, 'array'],
            'lender_reference' => [$need, 'string', 'max:180'],
            'funder_reference' => [$need, 'string', 'max:180'],
            'principal_reference' => [$need, 'string', 'max:180'],
        ];
    }

    private function key(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        abort_if($key === '' || strlen($key) > 160, 422, 'An Idempotency-Key header (up to 160 characters) is required.');

        return $key;
    }
}
