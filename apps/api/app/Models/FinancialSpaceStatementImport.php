<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialSpaceStatementImport extends Model
{
    protected $fillable = [
        'public_id','financial_space_id','treasury_account_id','imported_by_user_id',
        'source_format','original_filename','source_hash','period_start','period_end',
        'opening_balance_minor','closing_balance_minor','row_count','matched_count',
        'exception_count','status','confirmation_status','confirmed_by_user_id','confirmed_at','column_mapping','summary','review_todos',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_balance_minor' => 'integer',
            'closing_balance_minor' => 'integer',
            'row_count' => 'integer',
            'matched_count' => 'integer',
            'exception_count' => 'integer',
            'column_mapping' => 'array',
            'summary' => 'array',
            'review_todos' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function rows() { return $this->hasMany(FinancialSpaceStatementRow::class, 'statement_import_id'); }
    public function account() { return $this->belongsTo(FinancialSpaceTreasuryAccount::class, 'treasury_account_id'); }
}
