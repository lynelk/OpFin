<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceExport extends Model
{
    protected $fillable = [
        'compliance_report_id',
        'created_by',
        'format',
        'status',
        'storage_path',
        'manifest',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
