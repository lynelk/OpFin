<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityFinanceGuarantee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'community_finance_facility_id', 'guarantor_membership_id', 'guarantor_user_id', 'status',
        'guarantee_amount_minor', 'consent_reference', 'consented_at', 'released_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'guarantee_amount_minor' => 'integer',
            'consented_at' => 'datetime',
            'released_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function facility()
    {
        return $this->belongsTo(CommunityFinanceFacility::class, 'community_finance_facility_id');
    }
}
