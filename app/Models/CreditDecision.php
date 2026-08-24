<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditDecision extends Model
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_REFERRED = 'referred';

    protected $fillable = [
        'loan_application_id',
        'user_id',
        'crb_report_id',
        'decided_by',
        'status',
        'requested_amount_minor',
        'approved_amount_minor',
        'monthly_income_minor',
        'estimated_obligation_minor',
        'policy_version',
        'reason_codes',
        'decision_summary',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount_minor' => 'integer',
            'approved_amount_minor' => 'integer',
            'monthly_income_minor' => 'integer',
            'estimated_obligation_minor' => 'integer',
            'reason_codes' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function offers()
    {
        return $this->hasMany(CreditOffer::class);
    }
}
