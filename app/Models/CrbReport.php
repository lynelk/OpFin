<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrbReport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLEAR = 'clear';
    public const STATUS_ADVERSE = 'adverse';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'requested_by',
        'provider',
        'provider_reference',
        'status',
        'score',
        'risk_flags',
        'raw_response',
        'requested_at',
        'received_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'risk_flags' => 'array',
            'raw_response' => 'array',
            'requested_at' => 'datetime',
            'received_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
