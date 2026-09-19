<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialSpaceCapability extends Model
{
    protected $fillable = [
        'financial_space_id', 'capability_key', 'enabled', 'status', 'configuration',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'configuration' => 'array',
        ];
    }

    public function space()
    {
        return $this->belongsTo(FinancialSpace::class, 'financial_space_id');
    }
}
