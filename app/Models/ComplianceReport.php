<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceReport extends Model
{
    protected $fillable = [
        'report_type',
        'period_start',
        'period_end',
        'status',
        'generated_by',
        'parameters',
        'summary',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'parameters' => 'array',
            'summary' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
