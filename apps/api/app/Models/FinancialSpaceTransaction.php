<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialSpaceTransaction extends Model
{
    protected $fillable = [
        'public_id','financial_space_id','treasury_account_id','created_by_user_id',
        'transaction_reference','transaction_type','direction','amount_minor','currency',
        'description','counterparty_name','transaction_date','value_date','source_type',
        'source_reference','reconciliation_status','reconciled_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'transaction_date' => 'date',
            'value_date' => 'date',
            'reconciled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Treasury transactions are append-only. Post a correction instead of deleting.'));
    }

    public function account() { return $this->belongsTo(FinancialSpaceTreasuryAccount::class, 'treasury_account_id'); }
    public function space() { return $this->belongsTo(FinancialSpace::class, 'financial_space_id'); }
}
