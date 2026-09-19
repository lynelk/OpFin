<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialSpaceInvitation extends Model
{
    protected $fillable = [
        'financial_space_id', 'invited_by_user_id', 'recipient_phone', 'recipient_email',
        'intended_role', 'token_hash', 'status', 'expires_at', 'accepted_at',
        'accepted_by_user_id', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function space()
    {
        return $this->belongsTo(FinancialSpace::class, 'financial_space_id');
    }
}
