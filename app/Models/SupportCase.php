<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCase extends Model
{
    protected $fillable = [
        'customer_id',
        'assigned_to',
        'created_by',
        'case_number',
        'category',
        'status',
        'priority',
        'subject',
        'description',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
