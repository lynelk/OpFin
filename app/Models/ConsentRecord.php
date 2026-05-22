<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    public const PURPOSE_CREDIT_PROCESSING = 'credit_processing';
    public const STATUS_GRANTED = 'granted';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'purpose',
        'policy_version',
        'status',
        'channel',
        'granted_at',
        'revoked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
