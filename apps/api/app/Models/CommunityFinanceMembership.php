<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityFinanceMembership extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'community_finance_programme_id', 'user_id', 'employer_institution_id', 'member_number',
        'status', 'member_role', 'share_capital_required_minor', 'share_capital_balance_minor',
        'savings_balance_minor', 'joined_at', 'approved_at', 'suspended_at', 'terms_accepted_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'share_capital_required_minor' => 'integer',
            'share_capital_balance_minor' => 'integer',
            'savings_balance_minor' => 'integer',
            'joined_at' => 'datetime',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function programme()
    {
        return $this->belongsTo(CommunityFinanceProgramme::class, 'community_finance_programme_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employerInstitution()
    {
        return $this->belongsTo(Institution::class, 'employer_institution_id');
    }
}
