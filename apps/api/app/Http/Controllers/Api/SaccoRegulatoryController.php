<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Support\FinancialSpaceRolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaccoRegulatoryController extends Controller
{
    private const PRODUCT_TYPES = [
        'shares', 'savings', 'mandatory_savings', 'fixed_deposit', 'welfare', 'loan',
    ];

    private function officer(Request $request, FinancialSpace $space): void
    {
        abort_unless($space->type === 'sacco' && $space->status === 'active', 404);
        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('role');
        abort_unless(
            is_string($role) && FinancialSpaceRolePolicy::canManageFinancialRecords('sacco', $role),
            403
        );
    }

    public function attestations(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->officer($request, $space);

        return response()->json(['data' => [
            'attestations' => DB::table('sacco_regulatory_attestations')
                ->where('financial_space_id', $space->id)->orderByDesc('id')
                ->get(),
            'notice' => 'This is internal evidence review, not live verification against a regulator.',
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function submitAttestation(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->officer($request, $space);
        $data = $request->validate([
            'issuing_authority' => ['required', 'string', 'max:160'],
            'licence_category' => ['required', 'string', 'max:120'],
            'evidence_reference' => ['required', 'string', 'max:200'],
            'permitted_product_types' => ['required', 'array', 'min:1'],
            'permitted_product_types.*' => ['required', Rule::in(self::PRODUCT_TYPES)],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after:valid_from'],
        ]);
        $id = DB::table('sacco_regulatory_attestations')->insertGetId([
            ...$data,
            'financial_space_id' => $space->id,
            'jurisdiction' => $space->country,
            'permitted_product_types' => json_encode(array_values(array_unique($data['permitted_product_types']))),
            'review_status' => 'pending_review',
            'submitted_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => [
            'attestation_id' => $id,
            'review_status' => 'pending_review',
            'provider_activation_enabled' => false,
        ]], 201);
    }

    public function reviewAttestation(Request $request, FinancialSpace $space, int $attestation): JsonResponse
    {
        abort_unless($space->type === 'sacco' && $space->status === 'active', 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['reviewed', 'rejected'])],
        ]);
        $query = DB::table('sacco_regulatory_attestations')
            ->where('financial_space_id', $space->id)->where('id', $attestation);
        $record = $query->first();
        abort_unless($record, 404);
        abort_unless($record->review_status === 'pending_review', 409);
        abort_unless($record->submitted_by_user_id !== $request->user()->id, 403);
        $query->update([
            'review_status' => $data['decision'],
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => [
            'attestation_id' => $attestation,
            'review_status' => $data['decision'],
            'reviewed_document_only' => true,
        ]]);
    }

    public function activateProduct(Request $request, FinancialSpace $space, int $product): JsonResponse
    {
        $this->officer($request, $space);
        $data = $request->validate([
            'attestation_id' => ['required', 'integer'],
        ]);
        $active = DB::transaction(function () use ($request, $space, $product, $data) {
            $productQuery = DB::table('sacco_products')
                ->where('financial_space_id', $space->id)
                ->where('id', $product);
            $row = $productQuery->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->status === 'draft', 409);
            abort_unless($row->created_by_user_id !== null, 409, 'Legacy product requires independent review.');
            abort_unless($row->created_by_user_id !== $request->user()->id, 403);

            $attestation = DB::table('sacco_regulatory_attestations')
                ->where('financial_space_id', $space->id)
                ->where('id', $data['attestation_id'])
                ->where('jurisdiction', $space->country)
                ->where('review_status', 'reviewed')
                ->whereDate('valid_from', '<=', today()->toDateString())
                ->where(function ($query) {
                    $query->whereNull('expires_on')
                        ->orWhereDate('expires_on', '>=', today()->toDateString());
                })->first();
            abort_unless($attestation, 422, 'Current reviewed jurisdictional evidence is required.');
            $permitted = json_decode((string) $attestation->permitted_product_types, true);
            abort_unless(
                is_array($permitted) && in_array($row->product_type, $permitted, true),
                422,
                'This product type is not listed in the reviewed operating permissions.'
            );
            $productQuery->update([
                'status' => 'active',
                'activated_by_user_id' => $request->user()->id,
                'activation_attestation_id' => $attestation->id,
                'activated_at' => now(),
                'updated_at' => now(),
            ]);

            return $productQuery->first();
        });

        return response()->json(['data' => [
            'product' => $active,
            'regulator_live_verification' => false,
            'notice' => 'Internal review does not replace external licence, custodian or provider certification.',
        ]]);
    }
}
