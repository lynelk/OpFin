<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancingApplication extends Model
{
    protected $fillable = [
        'reference', 'financial_intent_id', 'financial_product_id', 'user_id', 'financial_space_id',
        'status', 'suitability_snapshot', 'eligibility_snapshot', 'risk_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'suitability_snapshot' => 'array',
            'eligibility_snapshot' => 'array',
            'risk_snapshot' => 'array',
        ];
    }
}
