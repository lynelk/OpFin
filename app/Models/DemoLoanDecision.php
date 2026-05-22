<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoLoanDecision extends Model
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'loan_application_id',
        'user_id',
        'status',
        'requested_amount_minor',
        'approved_amount_minor',
        'monthly_income_minor',
        'estimated_monthly_obligation_minor',
        'reason_codes',
        'decision_summary',
        'decided_at',
    ];

    protected $casts = [
        'reason_codes' => 'array',
        'decided_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function offer()
    {
        return $this->hasOne(DemoLoanOffer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
