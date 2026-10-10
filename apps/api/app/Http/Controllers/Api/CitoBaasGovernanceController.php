<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cito\CitoBaasGovernanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class CitoBaasGovernanceController extends Controller
{
    public function __construct(private readonly CitoBaasGovernanceService $governance) {}

    public function draft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operation' => ['required', Rule::in(['charge', 'usage', 'subscription'])],
            'idempotency_key' => 'required|string|min:8|max:128',
            'payload' => 'required|array',
        ]);
        try {
            $intent = $this->governance->draft(
                $request->user(), $data['operation'], $data['idempotency_key'], $data['payload']
            );
            unset($intent->request_payload);
            return response()->json(['data' => ['intent' => $intent]], 201);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }

    public function show(Request $request, int $intent): JsonResponse
    {
        try {
            return response()->json(['data' => ['intent' => $this->governance->find($request->user(), $intent)]]);
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    public function approve(Request $request, int $intent): JsonResponse
    {
        try {
            return response()->json(['data' => ['intent' => $this->governance->approveAndSubmit($request->user(), $intent)]]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (RuntimeException) {
            return response()->json(['message' => 'Cito Billing/BaaS is not authorised for this environment.'], 503);
        }
    }
}
