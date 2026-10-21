<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PersonalFinancialSpaceService;
use App\Support\FinancialSpaceRolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MyFinancialSpacesReportController extends Controller
{
    public function __construct(private readonly PersonalFinancialSpaceService $personalSpaces) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->personalSpaces->ensure($request->user());

        $spaces = DB::table('financial_space_memberships as membership')
            ->join('financial_spaces as space', 'space.id', '=', 'membership.financial_space_id')
            ->where('membership.user_id', $request->user()->id)
            ->where('membership.status', 'active')
            ->whereNull('membership.deleted_at')
            ->where('space.status', 'active')
            ->whereNull('space.deleted_at')
            ->select([
                'space.id', 'space.public_id', 'space.name', 'space.type',
                'space.country', 'space.currency', 'membership.role',
            ])->orderBy('space.id')->limit(200)->get();

        $reports = [];
        foreach ($spaces as $space) {
            if (! FinancialSpaceRolePolicy::canViewFinancialRecords($space->type, $space->role)) {
                // Employer membership never authorises access to the employer's
                // cash, debt, asset register or other corporate financial facts.
                continue;
            }

            $assets = (int) DB::table('financial_assets')
                ->where('financial_space_id', $space->id)
                ->where('status', 'active')->whereNull('deleted_at')
                ->sum('value_minor');
            $obligations = DB::table('financial_obligations')
                ->where('financial_space_id', $space->id)
                ->where('status', 'open')->whereNull('deleted_at')
                ->selectRaw('direction, SUM(outstanding_amount_minor) as total_minor')
                ->groupBy('direction')->get()->keyBy('direction');
            $debt = (int) ($obligations['i_owe']->total_minor ?? 0);
            $receivable = (int) ($obligations['owed_to_me']->total_minor ?? 0);
            $cash = (int) DB::table('financial_accounts')
                ->where('financial_space_id', $space->id)
                ->where('active', true)
                ->where('currency', $space->currency)
                ->sum('balance_minor');

            $reports[] = [
                'space_public_id' => $space->public_id,
                'space_name' => $space->name,
                'space_type' => $space->type,
                'space_label' => FinancialSpaceRolePolicy::label($space->type),
                'member_role' => $space->role,
                'currency' => $space->currency,
                'recorded_cash_minor' => $cash,
                'recorded_assets_minor' => $assets,
                'recorded_debt_minor' => $debt,
                'recorded_receivables_minor' => $receivable,
                'recorded_net_position_minor' => $cash + $assets + $receivable - $debt,
                'ownership_scope' => $space->type === 'personal'
                    ? 'personal' : 'separate_shared_or_organisation_space',
                'evidence_status' => 'recorded_not_independently_settled',
            ];
        }

        return response()->json(['data' => [
            'spaces' => $reports,
            'personal_aggregate_minor' => null,
            'note' => 'Space positions are separate legal ownership contexts. Group or corporate assets are not personal net worth. Different currencies and unverified cash sources are not summed.',
            'as_of' => now()->toIso8601String(),
        ]])->header('Cache-Control', 'private, no-store');
    }
}
