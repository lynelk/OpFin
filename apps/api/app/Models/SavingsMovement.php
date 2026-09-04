<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsMovement extends Model
{
    public const TYPE_CONTRIBUTION = 'contribution';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const STATUS_COLLECTION_PENDING = 'collection_pending';

    public const STATUS_COLLECTED_PENDING_PARTNER = 'collected_pending_partner';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_WITHDRAWAL_REQUESTED = 'withdrawal_requested';

    public const STATUS_PARTNER_RELEASED = 'partner_released';

    public const STATUS_PAYOUT_PENDING = 'payout_pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'savings_goal_id',
        'user_id',
        'institution_id',
        'mobile_money_transaction_id',
        'movement_reference',
        'movement_type',
        'status',
        'amount_minor',
        'currency',
        'idempotency_key',
        'partner_reference',
        'partner_evidence_hash',
        'requested_at',
        'provider_completed_at',
        'partner_confirmed_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'requested_at' => 'datetime',
            'provider_completed_at' => 'datetime',
            'partner_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function goal()
    {
        return $this->belongsTo(SavingsGoal::class, 'savings_goal_id');
    }

    public function mobileMoneyTransaction()
    {
        return $this->belongsTo(MobileMoneyTransaction::class);
    }
}
