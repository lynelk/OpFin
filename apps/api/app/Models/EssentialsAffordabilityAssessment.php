<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssentialsAffordabilityAssessment extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'financial_space_id',
        'essentials_account_id',
        'bill_plan_id',
        'bill_amount_minor',
        'bill_due_date',
        'horizon_end',
        'currency',
        'recorded_available_minor',
        'own_money_capacity_minor',
        'financing_gap_minor',
        'projected_income_minor',
        'scheduled_outflows_minor',
        'proposed_repayment_minor',
        'minimum_projected_balance_minor',
        'classification',
        'snapshot',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'bill_amount_minor' => 'integer',
            'bill_due_date' => 'date',
            'horizon_end' => 'date',
            'recorded_available_minor' => 'integer',
            'own_money_capacity_minor' => 'integer',
            'financing_gap_minor' => 'integer',
            'projected_income_minor' => 'integer',
            'scheduled_outflows_minor' => 'integer',
            'proposed_repayment_minor' => 'integer',
            'minimum_projected_balance_minor' => 'integer',
            'snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
