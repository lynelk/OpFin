<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityFinancePartnerPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'community_finance_programme_id', 'community_finance_membership_id', 'user_id', 'plan_type',
        'public_product_name', 'provider_name', 'status', 'asset_description', 'premium_amount_minor',
        'financed_amount_minor', 'deposit_amount_minor', 'currency', 'term_months', 'partner_reference',
        'terms', 'risk_controls', 'enrolled_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'premium_amount_minor' => 'integer',
            'financed_amount_minor' => 'integer',
            'deposit_amount_minor' => 'integer',
            'term_months' => 'integer',
            'terms' => 'array',
            'risk_controls' => 'array',
            'enrolled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
