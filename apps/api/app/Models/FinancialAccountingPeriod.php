<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialAccountingPeriod extends Model
{
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'period_start',
        'period_end',
        'status',
        'closed_by',
        'financial_integrity_run_id',
        'integrity_evidence_hash',
        'close_reason',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'closed_at' => 'datetime',
        ];
    }
}
