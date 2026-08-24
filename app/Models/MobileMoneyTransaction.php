<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileMoneyTransaction extends Model
{
    public const DIRECTION_DISBURSEMENT = 'disbursement';
    public const DIRECTION_COLLECTION = 'collection';
    public const DIRECTION_REVERSAL = 'reversal';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESSFUL = 'successful';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';

    public const RECONCILIATION_UNRECONCILED = 'unreconciled';
    public const RECONCILIATION_PENDING = 'pending';
    public const RECONCILIATION_MATCHED = 'matched';
    public const RECONCILIATION_EXCEPTION = 'exception';

    protected $fillable = [
        'transaction_id',
        'credit_offer_id',
        'loan_id',
        'user_id',
        'institution_id',
        'provider',
        'direction',
        'amount_minor',
        'currency',
        'phone',
        'idempotency_key',
        'internal_reference',
        'provider_reference',
        'status',
        'reconciliation_status',
        'failure_reason',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'webhook_event_id',
        'webhook_received_at',
        'last_status_checked_at',
        'metadata',
        'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'retry_count' => 'integer',
            'max_retries' => 'integer',
            'next_retry_at' => 'datetime',
            'webhook_received_at' => 'datetime',
            'last_status_checked_at' => 'datetime',
            'metadata' => 'array',
            'provider_payload' => 'array',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creditOffer()
    {
        return $this->belongsTo(CreditOffer::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->retry_count < $this->max_retries;
    }
}
