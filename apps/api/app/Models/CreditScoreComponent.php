<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditScoreComponent extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_ERROR = 'error';
    public const STATUS_UNAVAILABLE = 'unavailable';

    protected $fillable = [
        'user_id',
        'phone_number_id',
        'source',
        'status',
        'score',
        'weight_percent',
        'source_reference',
        'reason_codes',
        'raw_payload',
        'received_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'weight_percent' => 'float',
            'reason_codes' => 'array',
            'raw_payload' => 'array',
            'received_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
