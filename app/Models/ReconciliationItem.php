<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationItem extends Model
{
    public const STATUS_REQUIRES_PROVIDER_MATCH = 'requires_provider_match';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_EXCEPTION = 'exception';
    public const STATUS_WRITTEN_OFF = 'written_off';

    protected $fillable = [
        'reconciliation_run_id',
        'mobile_money_transaction_id',
        'provider_reference',
        'system_amount_minor',
        'provider_amount_minor',
        'status',
        'notes',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'system_amount_minor' => 'integer',
            'provider_amount_minor' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }
}
