<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShariaApproval extends Model
{
    protected $fillable = [
        'reference', 'authority_name', 'approval_reference', 'scope_type', 'scope_reference', 'status',
        'conditions', 'effective_from', 'expires_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['conditions' => 'array', 'effective_from' => 'datetime', 'expires_at' => 'datetime'];
    }
}
