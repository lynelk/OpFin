<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollDeductionEvent extends Model
{
    protected $fillable = [
        'payroll_deduction_case_id', 'correlation_id', 'idempotency_key', 'event_type', 'from_status',
        'to_status', 'actor_user_id', 'channel', 'evidence', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function payrollDeductionCase()
    {
        return $this->belongsTo(PayrollDeductionCase::class);
    }
}
