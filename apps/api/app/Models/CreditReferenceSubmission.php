<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditReferenceSubmission extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'loan_id',
        'credit_offer_id',
        'dedupe_key',
        'event_type',
        'information_type',
        'reporting_date',
        'status',
        'payload',
        'payload_hash',
        'provider_reference',
        'retry_count',
        'due_at',
        'last_attempt_at',
        'submitted_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'reporting_date' => 'date',
            'payload' => 'array',
            'retry_count' => 'integer',
            'due_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }
}
