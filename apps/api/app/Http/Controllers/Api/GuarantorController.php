<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Services\AuditLogger;
use App\Services\SmsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GuarantorController extends Controller
{
    public function __construct(
        private readonly SmsService $smsService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(LoanApplication $application, Request $request): JsonResponse
    {
        if ((int) $application->user_id !== (int) $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return ApiResponse::success('Guarantor contacts loaded.', [
            'guarantors' => DB::table('guarantor_contacts')
                ->where('loan_application_id', $application->id)
                ->get(['id', 'name', 'phone', 'relationship', 'status', 'verification_channel', 'confirmed_at', 'rejected_at']),
            'maximum_guarantors' => 2,
        ]);
    }

    public function store(LoanApplication $application, Request $request): JsonResponse
    {
        if ((int) $application->user_id !== (int) $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:160',
            'phone' => 'required|string|max:32',
            'relationship' => 'nullable|string|max:80',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $count = DB::table('guarantor_contacts')->where('loan_application_id', $application->id)->count();
        if ($count >= 2) {
            return ApiResponse::error('A loan may have no more than two guarantor contacts.', 422);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $id = DB::table('guarantor_contacts')->insertGetId([
            'loan_application_id' => $application->id,
            'borrower_user_id' => $application->user_id,
            'name' => $request->input('name'),
            'phone' => $request->input('phone'),
            'relationship' => $request->input('relationship'),
            'status' => 'pending',
            'verification_channel' => 'sms',
            'verification_token_hash' => hash('sha256', $code),
            'verification_expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->smsService->queueSms(
            (string) $request->input('phone'),
            'OpFin: You were named as a guarantor contact for loan request '.$application->id.'. Confirmation code: '.$code.'. Do not share this code with the borrower. If you did not agree, ignore it and contact OpFin.'
        );

        $this->auditLogger->record('credit.guarantor.invited', $request->user(), $application, [
            'guarantor_contact_id' => $id,
            'phone_last4' => substr((string) $request->input('phone'), -4),
            'contact_source' => 'borrower_manual_entry',
            'contact_list_accessed' => false,
        ], $request);

        return ApiResponse::success('Guarantor confirmation request sent.', [
            'guarantor' => DB::table('guarantor_contacts')->where('id', $id)->first(['id', 'name', 'phone', 'relationship', 'status']),
        ], 201);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guarantor_id' => 'required|integer|exists:guarantor_contacts,id',
            'code' => 'required|string|size:6',
            'decision' => 'required|in:confirm,reject',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $record = DB::table('guarantor_contacts')->where('id', $request->integer('guarantor_id'))->first();
        if (! $record || ! $record->verification_token_hash || now()->greaterThan($record->verification_expires_at)) {
            return ApiResponse::error('Confirmation code is invalid or expired.', 422);
        }
        if (! hash_equals($record->verification_token_hash, hash('sha256', (string) $request->input('code')))) {
            return ApiResponse::error('Confirmation code is invalid or expired.', 422);
        }

        $confirmed = $request->input('decision') === 'confirm';
        DB::table('guarantor_contacts')->where('id', $record->id)->update([
            'status' => $confirmed ? 'confirmed' : 'rejected',
            'confirmed_at' => $confirmed ? now() : null,
            'rejected_at' => $confirmed ? null : now(),
            'verification_token_hash' => null,
            'consent_evidence' => json_encode([
                'channel' => 'sms',
                'decision' => $request->input('decision'),
                'confirmed_at' => now()->toIso8601String(),
                'evidence_reference' => (string) Str::uuid(),
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return ApiResponse::success('Guarantor response recorded.', [
            'status' => $confirmed ? 'confirmed' : 'rejected',
        ]);
    }
}
