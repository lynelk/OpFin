<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityFinanceLedgerAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'community_finance_programme_id', 'community_finance_membership_id', 'user_id',
        'account_code', 'account_type', 'currency', 'balance_minor', 'status', 'restrictions',
    ];

    protected function casts(): array
    {
        return [
            'balance_minor' => 'integer',
            'restrictions' => 'array',
        ];
    }

    public function programme()
    {
        return $this->belongsTo(CommunityFinanceProgramme::class, 'community_finance_programme_id');
    }

    public function membership()
    {
        return $this->belongsTo(CommunityFinanceMembership::class, 'community_finance_membership_id');
    }

    public function entries()
    {
        return $this->hasMany(CommunityFinanceLedgerEntry::class, 'community_finance_ledger_account_id');
    }
}
