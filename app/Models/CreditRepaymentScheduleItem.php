<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditRepaymentScheduleItem extends Model
{
    public const STATUS_DUE = 'due';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'loan_id', 'credit_offer_id', 'installment_number', 'due_date', 'principal_minor', 'interest_minor',
        'fees_minor', 'total_due_minor', 'principal_outstanding_minor', 'interest_outstanding_minor',
        'fees_outstanding_minor', 'total_outstanding_minor', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'principal_minor' => 'integer',
            'interest_minor' => 'integer',
            'fees_minor' => 'integer',
            'total_due_minor' => 'integer',
            'principal_outstanding_minor' => 'integer',
            'interest_outstanding_minor' => 'integer',
            'fees_outstanding_minor' => 'integer',
            'total_outstanding_minor' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function loan() { return $this->belongsTo(Loan::class); }
    public function offer() { return $this->belongsTo(CreditOffer::class, 'credit_offer_id'); }
}
