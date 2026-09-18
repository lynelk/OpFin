<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanNplControl extends Model
{
    protected $fillable = [
        'loan_id',
        'non_performing_at',
        'principal_at_npl_minor',
        'initial_interest_minor',
        'penalty_interest_accrued_minor',
        'default_penalty_cap_minor',
        'recoverable_interest_cap_minor',
        'total_recoverable_cap_minor',
        'total_recovered_since_npl_minor',
        'enforcement_mode',
        'metadata',
        'last_evaluated_at',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    protected function casts(): array
    {
        return [
            'non_performing_at' => 'datetime',
            'principal_at_npl_minor' => 'integer',
            'initial_interest_minor' => 'integer',
            'penalty_interest_accrued_minor' => 'integer',
            'default_penalty_cap_minor' => 'integer',
            'recoverable_interest_cap_minor' => 'integer',
            'total_recoverable_cap_minor' => 'integer',
            'total_recovered_since_npl_minor' => 'integer',
            'metadata' => 'array',
            'last_evaluated_at' => 'datetime',
        ];
    }
}
