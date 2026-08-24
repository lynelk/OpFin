<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportCase;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CustomerSupportController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $cases = SupportCase::query()
            ->where('customer_id', $request->user()->id)
            ->with(['notes' => fn ($query) => $query->where('is_internal', false)->latest()])
            ->latest()
            ->limit(50)
            ->get();

        return ApiResponse::success('Your support cases loaded.', [
            'support_cases' => $cases,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:64',
            'subject' => 'required|string|max:160',
            'description' => 'required|string|max:4000',
            'related_reference' => 'nullable|string|max:160',
            'related_type' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $case = SupportCase::create([
            'customer_id' => $request->user()->id,
            'created_by' => $request->user()->id,
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'category' => $request->input('category'),
            'status' => SupportCase::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => $request->input('subject'),
            'description' => $request->input('description'),
        ]);

        $this->auditLogger->record('support.case.customer_created', $request->user(), $case, [
            'category' => $case->category,
            'related_reference' => $request->input('related_reference'),
            'related_type' => $request->input('related_type'),
        ], $request);

        return ApiResponse::success('Support case created.', [
            'support_case' => $case,
        ], 201);
    }
}
