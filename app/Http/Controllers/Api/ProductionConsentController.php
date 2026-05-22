<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductionConsentController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success('Consent records loaded.', [
            'consents' => ConsentRecord::where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function grant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'purpose' => 'required|string|max:64',
            'policy_version' => 'required|string|max:32',
            'channel' => 'nullable|string|max:32',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        ConsentRecord::where('user_id', $request->user()->id)
            ->where('purpose', $request->input('purpose'))
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->update(['status' => ConsentRecord::STATUS_REVOKED, 'revoked_at' => now()]);

        $consent = ConsentRecord::create([
            ...$validator->validated(),
            'user_id' => $request->user()->id,
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => $request->input('channel', 'api'),
            'granted_at' => now(),
        ]);

        $this->auditLogger->record('consent.granted', $request->user(), $consent, ['purpose' => $consent->purpose], $request);

        return ApiResponse::success('Consent granted.', ['consent' => $consent], 201);
    }

    public function revoke(ConsentRecord $consent, Request $request): JsonResponse
    {
        if ($consent->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $consent->update([
            'status' => ConsentRecord::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        $this->auditLogger->record('consent.revoked', $request->user(), $consent, ['purpose' => $consent->purpose], $request);

        return ApiResponse::success('Consent revoked.', ['consent' => $consent->fresh()]);
    }
}
