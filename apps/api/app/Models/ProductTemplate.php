<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTemplate extends Model
{
    protected $fillable = [
        'reference', 'code', 'version', 'name', 'family', 'rail', 'contract_types', 'guardrails', 'policy_reference',
        'status', 'created_by', 'idempotency_key', 'approved_by', 'approved_at', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'contract_types' => 'array',
            'guardrails' => 'array',
            'approved_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }
}
