<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityFinanceLedgerEntry extends Model
{
    protected $fillable = [
        'community_finance_programme_id', 'community_finance_ledger_account_id', 'community_finance_membership_id',
        'user_id', 'entry_type', 'direction', 'amount_minor', 'currency', 'source_type', 'source_id',
        'idempotency_key', 'description', 'metadata', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'source_id' => 'integer',
            'metadata' => 'array',
            'posted_at' => 'datetime',
        ];
    }

    public function account()
    {
        return $this->belongsTo(CommunityFinanceLedgerAccount::class, 'community_finance_ledger_account_id');
    }

    public function programme()
    {
        return $this->belongsTo(CommunityFinanceProgramme::class, 'community_finance_programme_id');
    }
}
