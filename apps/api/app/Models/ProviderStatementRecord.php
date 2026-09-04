<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderStatementRecord extends Model
{
    protected $fillable = [
        'reconciliation_run_id',
        'record_hash',
        'provider_reference',
        'internal_reference',
        'amount_minor',
        'currency',
        'direction',
        'provider_status',
        'occurred_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'occurred_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function run()
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }
}
