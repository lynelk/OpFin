<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssentialsCreditLine extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'financial_space_id', 'lender_partner_id', 'partner_product_id',
        'funding_pool_id', 'approved_limit_minor', 'available_limit_minor', 'outstanding_minor',
        'currency', 'status', 'decision_route', 'decision_reference', 'decision_snapshot', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_limit_minor' => 'integer',
            'available_limit_minor' => 'integer',
            'outstanding_minor' => 'integer',
            'decision_snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
