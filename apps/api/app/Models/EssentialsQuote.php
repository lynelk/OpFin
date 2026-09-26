<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssentialsQuote extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'financial_space_id', 'essentials_account_id', 'credit_line_id',
        'source_partner_account_id', 'source_platform', 'purpose_category', 'amount_minor',
        'interest_minor', 'fees_minor', 'total_repayment_minor', 'term_days', 'currency',
        'lender_partner_id', 'partner_product_id', 'funding_pool_id', 'status',
        'disclosure_snapshot', 'disclosure_hash', 'partner_authorisation_hash',
        'partner_authorisation_expires_at', 'expires_at', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'interest_minor' => 'integer',
            'fees_minor' => 'integer',
            'total_repayment_minor' => 'integer',
            'term_days' => 'integer',
            'disclosure_snapshot' => 'array',
            'partner_authorisation_expires_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
