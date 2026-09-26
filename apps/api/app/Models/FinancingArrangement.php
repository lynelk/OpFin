<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancingArrangement extends Model
{
    protected $fillable = [
        'reference', 'financing_application_id', 'financial_product_id', 'user_id', 'financial_space_id',
        'funding_pool_id', 'contract_type', 'principal_or_cost_minor', 'total_obligation_minor', 'currency',
        'status', 'legacy_type', 'legacy_id', 'contract_snapshot', 'contract_hash', 'activated_at', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'principal_or_cost_minor' => 'integer',
            'total_obligation_minor' => 'integer',
            'legacy_id' => 'integer',
            'contract_snapshot' => 'array',
            'activated_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }
}
