<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssentialsRepayment extends Model
{
    protected $fillable = [
        'reference', 'advance_id', 'user_id', 'amount_minor', 'principal_applied_minor',
        'currency', 'status', 'idempotency_key', 'cpay_reference', 'provider_reference',
        'paid_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'principal_applied_minor' => 'integer',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
