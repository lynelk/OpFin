<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialIntent;
use App\Models\FinancialProduct;
use App\Services\FinancingService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FinancingController extends Controller
{
    public function __construct(private readonly FinancingService $financing) {}

    public function intents(Request $request)
    {
        return response()->json(['data' => FinancialIntent::where('user_id', $request->user()->id)->latest()->get()]);
    }

    public function createIntent(Request $request)
    {
        $data = $request->validate([
            'financial_space_id' => ['required','integer'],
            'need_type' => ['required','string','max:80'],
            'principles_preference' => ['nullable','in:ALL_SUITABLE,SHARIA_ONLY,CONVENTIONAL_ONLY'],
            'amount_minor' => ['nullable','integer','min:1'],
            'currency' => ['nullable','string','size:3'],
            'purpose' => ['nullable','array'],
            'expires_at' => ['nullable','date','after:now'],
        ]);
        try {
            $intent = $this->financing->createIntent($request->user(), $data);
            return response()->json(['data' => $intent], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function matches(Request $request)
    {
        $data = $request->validate(['financial_intent_id' => ['required','integer']]);
        $intent = FinancialIntent::where('user_id', $request->user()->id)->findOrFail($data['financial_intent_id']);
        return response()->json(['data' => $this->financing->matchingProducts($intent)]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'financial_intent_id' => ['required','integer'],
            'financial_product_id' => ['required','integer'],
        ]);
        $intent = FinancialIntent::where('user_id', $request->user()->id)->findOrFail($data['financial_intent_id']);
        $product = FinancialProduct::findOrFail($data['financial_product_id']);
        try {
            return response()->json(['data' => $this->financing->apply($request->user(), $intent, $product)], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
