<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoLoanOffer extends Model
{
    public const STATUS_PENDING = 'pending_acceptance';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'demo_loan_decision_id',
        'loan_application_id',
        'user_id',
        'status',
        'principal_amount_minor',
        'total_repayment_minor',
        'duration_days',
        'interest_rate',
        'interest_type',
        'repayment_frequency',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function decision()
    {
        return $this->belongsTo(DemoLoanDecision::class, 'demo_loan_decision_id');
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
