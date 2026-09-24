<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialSpaceStatementRow extends Model
{
    protected $fillable = [
        'statement_import_id','financial_space_id','treasury_account_id','matched_transaction_id',
        'row_hash','statement_reference','transaction_date','value_date','description','direction',
        'amount_minor','currency','running_balance_minor','reconciliation_status','exception_type',
        'match_method','match_confidence_percent','suggested_matches','user_resolution','resolved_by_user_id','resolved_at','notes','raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'value_date' => 'date',
            'amount_minor' => 'integer',
            'running_balance_minor' => 'integer',
            'match_confidence_percent' => 'integer',
            'suggested_matches' => 'array',
            'resolved_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (FinancialSpaceStatementRow $row) {
            $evidenceFields = [
                'statement_import_id',
                'financial_space_id',
                'treasury_account_id',
                'row_hash',
                'statement_reference',
                'transaction_date',
                'value_date',
                'description',
                'direction',
                'amount_minor',
                'currency',
                'running_balance_minor',
                'raw_payload',
            ];

            foreach ($evidenceFields as $field) {
                if ($row->isDirty($field)) {
                    throw new \LogicException(
                        'Imported statement evidence is immutable. Re-import corrected provider evidence instead.'
                    );
                }
            }
        });

        static::deleting(fn () => throw new \LogicException(
            'Imported statement rows are reconciliation evidence and cannot be deleted.'
        ));
    }

    public function matchedTransaction() { return $this->belongsTo(FinancialSpaceTransaction::class, 'matched_transaction_id'); }
}
