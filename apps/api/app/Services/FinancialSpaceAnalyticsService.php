<?php

namespace App\Services;

use App\Models\FinancialSpace;
use Illuminate\Support\Facades\DB;

class FinancialSpaceAnalyticsService
{
    public function snapshot(FinancialSpace $space): array
    {
        $actions=DB::table('financial_space_action_intents')->where('financial_space_id',$space->id);
        $obligations=DB::table('financial_obligations')->where('financial_space_id',$space->id)->whereNull('deleted_at');
        $assets=DB::table('financial_assets')->where('financial_space_id',$space->id)->whereNull('deleted_at');

        return [
            'space'=>['id'=>$space->id,'type'=>$space->type,'name'=>$space->name,'currency'=>$space->currency],
            'members'=>[
                'active'=>(int)DB::table('financial_space_memberships')->where('financial_space_id',$space->id)->where('status','active')->whereNull('deleted_at')->count(),
            ],
            'operations'=>[
                'total'=>(int)(clone $actions)->count(),
                'pending_approval'=>(int)(clone $actions)->where('status','pending_approval')->count(),
                'submitted'=>(int)(clone $actions)->where('status','submitted')->count(),
                'principal_minor'=>(int)(clone $actions)->sum('principal_amount_minor'),
                'platform_fees_minor'=>(int)(clone $actions)->sum('platform_fee_minor'),
                'collections_minor'=>(int)(clone $actions)->where('direction','collection')->sum('principal_amount_minor'),
                'disbursements_minor'=>(int)(clone $actions)->where('direction','disbursement')->sum('principal_amount_minor'),
            ],
            'obligations'=>[
                'open_count'=>(int)(clone $obligations)->where('status','open')->count(),
                'outstanding_minor'=>(int)(clone $obligations)->where('status','open')->sum('outstanding_amount_minor'),
                'overdue_minor'=>(int)(clone $obligations)->where('status','open')->whereNotNull('due_date')->where('due_date','<',now()->toDateString())->sum('outstanding_amount_minor'),
            ],
            'assets'=>[
                'active_count'=>(int)(clone $assets)->where('status','active')->count(),
                'recorded_value_minor'=>(int)(clone $assets)->where('status','active')->sum('value_minor'),
            ],
            'commercial'=>[
                'active_subscription'=>DB::table('subscription_contracts')->where('financial_space_id',$space->id)->where('status','active')->latest('id')->first(),
                'revenue_minor'=>(int)DB::table('revenue_events')->where('financial_space_id',$space->id)->whereIn('status',['accrued','settled'])->sum('opfin_amount_minor'),
            ],
            'boundary'=>'Recorded operational analytics are not bank/custodian confirmation. Investment-club NAV and ownership analytics remain authoritative in the club accounting book/report endpoints.',
        ];
    }
}
