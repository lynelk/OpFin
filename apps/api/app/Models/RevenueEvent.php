<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueEvent extends Model
{
    protected $fillable = [
        'public_id', 'occurrence_key', 'financial_space_id', 'user_id', 'partner_id',
        'partner_product_id', 'commercial_agreement_id', 'ledger_transaction_id',
        'event_type', 'source_type', 'source_reference', 'gross_amount_minor',
        'opfin_amount_minor', 'partner_amount_minor', 'tax_amount_minor', 'currency',
        'status', 'accounting_status', 'statement_reconciliation_status',
        'cpay_reference', 'reconciliation_reference', 'occurred_at', 'settled_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount_minor' => 'integer',
            'opfin_amount_minor' => 'integer',
            'partner_amount_minor' => 'integer',
            'tax_amount_minor' => 'integer',
            'occurred_at' => 'datetime',
            'settled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
