<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsGoal extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'institution_id',
        'savings_product_id',
        'goal_reference',
        'name',
        'target_amount_minor',
        'target_date',
        'status',
        'scheduled_amount_minor',
        'contribution_frequency',
        'autopilot_enabled',
        'paused_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_amount_minor' => 'integer',
            'target_date' => 'date',
            'scheduled_amount_minor' => 'integer',
            'autopilot_enabled' => 'boolean',
            'paused_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(SavingsProduct::class, 'savings_product_id');
    }

    public function movements()
    {
        return $this->hasMany(SavingsMovement::class);
    }

    public function confirmedBalanceMinor(): int
    {
        $contributions = (int) $this->movements()
            ->where('movement_type', SavingsMovement::TYPE_CONTRIBUTION)
            ->where('status', SavingsMovement::STATUS_CONFIRMED)
            ->sum('amount_minor');
        $withdrawals = (int) $this->movements()
            ->where('movement_type', SavingsMovement::TYPE_WITHDRAWAL)
            ->where('status', SavingsMovement::STATUS_PAID)
            ->sum('amount_minor');

        return max(0, $contributions - $withdrawals);
    }

    public function reservedWithdrawalMinor(): int
    {
        return (int) $this->movements()
            ->where('movement_type', SavingsMovement::TYPE_WITHDRAWAL)
            ->whereIn('status', [
                SavingsMovement::STATUS_WITHDRAWAL_REQUESTED,
                SavingsMovement::STATUS_PARTNER_RELEASED,
                SavingsMovement::STATUS_PAYOUT_PENDING,
            ])
            ->sum('amount_minor');
    }

    public function availableBalanceMinor(): int
    {
        return max(0, $this->confirmedBalanceMinor() - $this->reservedWithdrawalMinor());
    }
}
