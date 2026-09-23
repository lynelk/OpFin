<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssentialsAdvance extends Model
{
    protected $fillable = [
        'reference', 'quote_id', 'user_id', 'financial_space_id', 'financial_obligation_id', 'essentials_account_id',
        'lender_partner_id', 'partner_product_id', 'funding_pool_id', 'principal_minor',
        'principal_outstanding_minor', 'total_repayment_minor', 'outstanding_minor',
        'repaid_minor', 'currency', 'status', 'lender_contract_reference',
        'biller_payment_reference', 'fulfilment_payload', 'repayment_schedule',
        'next_due_date', 'final_due_date', 'activated_at', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'principal_minor' => 'integer',
            'principal_outstanding_minor' => 'integer',
            'total_repayment_minor' => 'integer',
            'outstanding_minor' => 'integer',
            'repaid_minor' => 'integer',
            'fulfilment_payload' => 'array',
            'repayment_schedule' => 'array',
            'next_due_date' => 'date',
            'final_due_date' => 'date',
            'activated_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }
}
