<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceCredential;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinancialSpaceCredentialController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->assertMember($request, $space);

        return response()->json([
            'data' => [
                'credentials' => $space->credentials()
                    ->orderBy('credential_type')
                    ->orderBy('issuer_name')
                    ->get(),
            ],
        ]);
    }

    public function store(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->assertAdministrator($request, $space);
        abort_if($space->type === 'personal', 422, 'Personal identity credentials are managed through the individual identity and KYC domain.');

        $validated = $request->validate([
            'credential_type' => ['required', 'string', 'max:100'],
            'issuer_code' => ['required', 'string', 'max:100'],
            'issuer_name' => ['required', 'string', 'max:160'],
            'credential_value' => ['required', 'string', 'max:255'],
            'jurisdiction_country' => ['sometimes', 'string', 'size:2'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:issued_at'],
            'metadata' => ['nullable', 'array'],
        ]);

        $credential = FinancialSpaceCredential::query()->updateOrCreate(
            [
                'financial_space_id' => $space->id,
                'credential_type' => strtolower($validated['credential_type']),
                'issuer_code' => strtoupper($validated['issuer_code']),
            ],
            [
                'issuer_name' => trim($validated['issuer_name']),
                'credential_value' => trim($validated['credential_value']),
                'jurisdiction_country' => strtoupper($validated['jurisdiction_country'] ?? $space->country),
                'verification_status' => 'declared',
                'issued_at' => $validated['issued_at'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'verified_at' => null,
                'verified_by_user_id' => null,
                'verification_reference' => null,
                'verification_evidence_hash' => null,
                'metadata' => $validated['metadata'] ?? null,
            ]
        );

        $this->auditLogger->record('financial_space.credential.declared', $request->user(), $credential, [
            'financial_space_id' => $space->id,
            'credential_type' => $credential->credential_type,
            'issuer_code' => $credential->issuer_code,
            'verification_status' => 'declared',
        ], $request);

        return response()->json(
            ['data' => ['credential' => $credential->fresh()]],
            $credential->wasRecentlyCreated ? 201 : 200
        );
    }

    public function verify(Request $request, FinancialSpaceCredential $credential): JsonResponse
    {
        $validated = $request->validate([
            'verification_status' => ['required', Rule::in(['verified', 'rejected', 'expired'])],
            'verification_reference' => ['nullable', 'string', 'max:255'],
            'verification_evidence_hash' => ['nullable', 'string', 'size:64', 'regex:/^[a-fA-F0-9]{64}$/'],
        ]);

        $credential->update([
            'verification_status' => $validated['verification_status'],
            'verified_at' => now(),
            'verified_by_user_id' => $request->user()->id,
            'verification_reference' => $validated['verification_reference'] ?? null,
            'verification_evidence_hash' => isset($validated['verification_evidence_hash'])
                ? strtolower($validated['verification_evidence_hash'])
                : null,
        ]);

        $this->auditLogger->record('financial_space.credential.verified', $request->user(), $credential, [
            'financial_space_id' => $credential->financial_space_id,
            'verification_status' => $credential->verification_status,
            'verification_reference' => $credential->verification_reference,
        ], $request);

        return response()->json(['data' => ['credential' => $credential->fresh()]]);
    }

    private function assertMember(Request $request, FinancialSpace $space): void
    {
        abort_unless(
            DB::table('financial_space_memberships')
                ->where('financial_space_id', $space->id)
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->exists(),
            403
        );
    }

    private function assertAdministrator(Request $request, FinancialSpace $space): void
    {
        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->value('role');

        abort_unless(
            in_array($role, ['owner', 'administrator', 'admin', 'chairperson', 'treasurer', 'secretary', 'director', 'manager'], true),
            403
        );
    }
}
