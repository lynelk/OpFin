<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityFinanceProgramme extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'internal_key', 'public_name', 'plain_language_name', 'programme_type', 'status',
        'operating_model', 'country', 'currency', 'regulated_partner_name', 'activation_gate',
        'activation_requirements', 'eligibility_rules', 'risk_controls', 'terms', 'disclosures',
        'activated_at', 'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'activation_requirements' => 'array',
            'eligibility_rules' => 'array',
            'risk_controls' => 'array',
            'terms' => 'array',
            'disclosures' => 'array',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function memberships()
    {
        return $this->hasMany(CommunityFinanceMembership::class);
    }

    public function ledgerAccounts()
    {
        return $this->hasMany(CommunityFinanceLedgerAccount::class);
    }
}
