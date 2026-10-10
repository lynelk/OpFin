<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\FinancialSpacePayoutMandateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FinancialSpacePayoutMandateController extends Controller
{
    public function __construct(private readonly FinancialSpacePayoutMandateService $mandates) {}

    public function store(Request $request, FinancialSpace $space): JsonResponse
    {
        $data = $request->validate([
            'currency' => 'required|string|size:3',
            'custody_agreement_reference' => 'required|string|max:128',
            'segregated_settlement_account_reference' => 'required|string|max:128',
            'document_sha256' => 'required|string|size:64',
            'expires_at' => 'required|date|after:now',
        ]);
        try {
            return response()->json([
                'data' => ['mandate' => $this->mandates->submit($space, $request->user(), $data)],
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }

    public function approve(Request $request, FinancialSpace $space, int $mandate): JsonResponse
    {
        try {
            return response()->json([
                'data' => ['mandate' => $this->mandates->approve($space, $request->user(), $mandate)],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }
}
