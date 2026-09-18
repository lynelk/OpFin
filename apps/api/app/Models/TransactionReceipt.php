<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionReceipt extends Model
{
    protected $fillable = [
        'user_id',
        'loan_id',
        'mobile_money_transaction_id',
        'receipt_reference',
        'transaction_type',
        'amount_minor',
        'currency',
        'provider',
        'provider_reference',
        'status',
        'payload',
        'payload_hash',
        'delivery_channel',
        'issued_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'payload' => 'array',
            'issued_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
