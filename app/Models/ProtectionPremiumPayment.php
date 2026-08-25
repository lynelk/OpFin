<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProtectionPremiumPayment extends Model
{
    public const STATUS_COLLECTION_PENDING = 'collection_pending';

    public const STATUS_COLLECTED_PENDING_PARTNER = 'collected_pending_partner';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'protection_policy_id',
        'user_id',
        'institution_id',
        'mobile_money_transaction_id',
        'payment_reference',
        'idempotency_key',
        'status',
        'amount_minor',
        'currency',
        'coverage_period_start',
        'coverage_period_end',
        'partner_reference',
        'partner_evidence_hash',
        'requested_at',
        'provider_completed_at',
        'partner_confirmed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'coverage_period_start' => 'date',
            'coverage_period_end' => 'date',
            'requested_at' => 'datetime',
            'provider_completed_at' => 'datetime',
            'partner_confirmed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function policy()
    {
        return $this->belongsTo(ProtectionPolicy::class, 'protection_policy_id');
    }

    public function mobileMoneyTransaction()
    {
        return $this->belongsTo(MobileMoneyTransaction::class);
    }
}
