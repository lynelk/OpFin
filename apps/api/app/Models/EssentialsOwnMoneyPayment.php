<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssentialsOwnMoneyPayment extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'financial_space_id',
        'essentials_account_id',
        'bill_plan_id',
        'affordability_assessment_id',
        'wallet_id',
        'collection_transaction_id',
        'amount_minor',
        'currency',
        'status',
        'idempotency_key',
        'provider_reference',
        'evidence',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'evidence' => 'array',
            'settled_at' => 'datetime',
        ];
    }
}
