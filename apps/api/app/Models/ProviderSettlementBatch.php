<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderSettlementBatch extends Model
{
    protected $fillable = [
        'reconciliation_run_id',
        'provider',
        'currency',
        'business_date',
        'collections_minor',
        'disbursements_minor',
        'provider_fee_minor',
        'bank_net_settlement_minor',
        'bank_reference',
        'evidence',
        'evidence_hash',
        'ledger_transaction_id',
        'settled_by',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'collections_minor' => 'integer',
            'disbursements_minor' => 'integer',
            'provider_fee_minor' => 'integer',
            'bank_net_settlement_minor' => 'integer',
            'evidence' => 'array',
            'settled_at' => 'datetime',
        ];
    }
}
