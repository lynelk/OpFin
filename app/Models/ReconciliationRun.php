<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationRun extends Model
{
    protected $fillable = ['provider', 'business_date', 'status', 'created_by', 'started_at', 'completed_at', 'summary'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'summary' => 'array',
        ];
    }
}
