<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditWriteOff extends Model
{
    protected $fillable = [
        'loan_id',
        'principal_written_off_minor',
        'financed_fee_written_off_minor',
        'currency',
        'policy_version',
        'evidence',
        'legal_obligation_preserved',
        'approved_by',
        'impairment_assessment_id',
        'ledger_transaction_id',
        'written_off_at',
    ];

    protected function casts(): array
    {
        return [
            'principal_written_off_minor' => 'integer',
            'financed_fee_written_off_minor' => 'integer',
            'evidence' => 'array',
            'legal_obligation_preserved' => 'boolean',
            'written_off_at' => 'datetime',
        ];
    }
}
