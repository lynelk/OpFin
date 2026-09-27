<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollDeductionCase extends Model
{
    protected $fillable = [
        'reference', 'financing_application_id', 'user_id', 'financial_space_id', 'scheme', 'provider',
        'vote_code', 'vote_name', 'employment_reference_hash', 'status', 'affordability_status',
        'affordable_amount_minor', 'requested_deduction_minor', 'currency', 'provider_agreement_reference',
        'reservation_reference', 'reservation_expires_at', 'undertaking_consent_record_id', 'key_facts_snapshot',
        'provider_state', 'last_provider_reference', 'rejection_code', 'rejection_reason', 'approved_at',
        'payroll_submitted_at', 'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'affordable_amount_minor' => 'integer',
            'requested_deduction_minor' => 'integer',
            'reservation_expires_at' => 'datetime',
            'key_facts_snapshot' => 'array',
            'provider_state' => 'array',
            'approved_at' => 'datetime',
            'payroll_submitted_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function financingApplication()
    {
        return $this->belongsTo(FinancingApplication::class);
    }

    public function events()
    {
        return $this->hasMany(PayrollDeductionEvent::class);
    }

    public function reconciliations()
    {
        return $this->hasMany(PayrollDeductionReconciliation::class);
    }
}
