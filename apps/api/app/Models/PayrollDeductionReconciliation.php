<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollDeductionReconciliation extends Model
{
    protected $fillable = [
        'payroll_deduction_case_id', 'payroll_period', 'expected_minor', 'recovered_minor', 'variance_minor',
        'currency', 'status', 'result_category', 'provider_reference', 'evidence', 'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_minor' => 'integer',
            'recovered_minor' => 'integer',
            'variance_minor' => 'integer',
            'evidence' => 'array',
            'reconciled_at' => 'datetime',
        ];
    }

    public function payrollDeductionCase()
    {
        return $this->belongsTo(PayrollDeductionCase::class);
    }
}
