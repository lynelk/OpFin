<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditOffer extends Model
{
    public const STATUS_OFFERED = 'offered';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_DISBURSEMENT_PENDING = 'disbursement_pending';

    public const STATUS_DISBURSED = 'disbursed';

    public const STATUS_DISBURSEMENT_FAILED = 'disbursement_failed';

    protected $fillable = [
        'loan_application_id',
        'credit_decision_id',
        'user_id',
        'institution_id',
        'created_by',
        'offer_reference',
        'version',
        'status',
        'currency',
        'principal_amount_minor',
        'interest_amount_minor',
        'fees_minor',
        'net_disbursement_minor',
        'total_repayment_minor',
        'duration_days',
        'interest_rate_percent',
        'interest_cycle',
        'interest_type',
        'repayment_frequency',
        'fee_treatment',
        'policy_version',
        'pricing_snapshot',
        'disclosure_snapshot',
        'offered_at',
        'expires_at',
        'accepted_at',
        'withdrawn_at',
        'acceptance_metadata',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'principal_amount_minor' => 'integer',
            'interest_amount_minor' => 'integer',
            'fees_minor' => 'integer',
            'net_disbursement_minor' => 'integer',
            'total_repayment_minor' => 'integer',
            'duration_days' => 'integer',
            'interest_rate_percent' => 'decimal:6',
            'pricing_snapshot' => 'array',
            'disclosure_snapshot' => 'array',
            'acceptance_metadata' => 'array',
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function decision()
    {
        return $this->belongsTo(CreditDecision::class, 'credit_decision_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function mobileMoneyTransactions()
    {
        return $this->hasMany(MobileMoneyTransaction::class);
    }
}
