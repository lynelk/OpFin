<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialIntent extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'financial_space_id', 'need_type', 'principles_preference',
        'amount_minor', 'currency', 'purpose', 'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'purpose' => 'array', 'expires_at' => 'datetime'];
    }
}
