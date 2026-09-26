<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssentialsPartnerAuthorisation extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'partner_account_id', 'financial_space_id',
        'scopes', 'status', 'granted_at', 'expires_at', 'revoked_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
