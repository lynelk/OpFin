<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityFinanceFacility extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'community_finance_programme_id', 'community_finance_membership_id', 'user_id', 'employer_institution_id',
        'credit_decision_id', 'facility_type', 'public_product_name', 'status', 'requested_amount_minor',
        'approved_amount_minor', 'currency', 'tenor_days', 'pricing_summary', 'eligibility_snapshot',
        'decision_status', 'source_lender_type', 'approved_at', 'disbursed_at', 'closed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount_minor' => 'integer',
            'approved_amount_minor' => 'integer',
            'tenor_days' => 'integer',
            'pricing_summary' => 'array',
            'eligibility_snapshot' => 'array',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function guarantees()
    {
        return $this->hasMany(CommunityFinanceGuarantee::class);
    }
}
