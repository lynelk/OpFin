<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanImpairmentAssessment extends Model
{
    protected $fillable = [
        'loan_id',
        'as_of_date',
        'stage',
        'gross_exposure_minor',
        'expected_credit_loss_minor',
        'currency',
        'policy_version',
        'evidence',
        'approved_by',
        'ledger_transaction_id',
        'assessed_at',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'gross_exposure_minor' => 'integer',
            'expected_credit_loss_minor' => 'integer',
            'evidence' => 'array',
            'assessed_at' => 'datetime',
        ];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
