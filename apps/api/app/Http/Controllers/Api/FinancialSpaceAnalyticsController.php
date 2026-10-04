<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\FinancialSpaceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialSpaceAnalyticsController extends Controller
{
    public function __construct(private readonly FinancialSpaceAnalyticsService $analytics) {}

    public function show(Request $request, FinancialSpace $space): JsonResponse
    {
        abort_unless(DB::table('financial_space_memberships')->where('financial_space_id',$space->id)->where('user_id',$request->user()->id)->where('status','active')->whereNull('deleted_at')->exists(),403);
        return response()->json(['data'=>$this->analytics->snapshot($space)]);
    }
}
