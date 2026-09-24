<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialSpaceGeneratedStatement extends Model
{
    protected $fillable = [
        'public_id','statement_number','financial_space_id','treasury_account_id','generated_by_user_id',
        'period_start','period_end','opening_balance_minor','closing_balance_minor','total_debits_minor',
        'total_credits_minor','transaction_count','reconciliation_status','content_hash','generated_at','summary',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_balance_minor' => 'integer',
            'closing_balance_minor' => 'integer',
            'total_debits_minor' => 'integer',
            'total_credits_minor' => 'integer',
            'transaction_count' => 'integer',
            'generated_at' => 'datetime',
            'summary' => 'array',
        ];
    }
}
