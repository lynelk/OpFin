<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EssentialsBillPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference',
        'user_id',
        'financial_space_id',
        'essentials_account_id',
        'expected_amount_minor',
        'currency',
        'frequency',
        'next_due_date',
        'necessary',
        'reminder_days_before',
        'active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount_minor' => 'integer',
            'next_due_date' => 'date',
            'necessary' => 'boolean',
            'reminder_days_before' => 'integer',
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
