<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanProductTerm extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'loan_product_id',
        'interest_rate',
        'interest_type',
        'interest_cycle',
        'repayment_frequency',
        'duration',
        'status',
        'default_interest_rate',
        'default_interest_cycle',
        'umra_interest_approval_reference',
        'umra_interest_approved_at',
        'regulatory_approval_reference',
        'regulatory_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:6',
            'default_interest_rate' => 'decimal:6',
            'umra_interest_approved_at' => 'datetime',
            'regulatory_approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (LoanProductTerm $term) {
            $rateChanged = $term->isDirty(['interest_rate', 'default_interest_rate', 'interest_cycle', 'default_interest_cycle']);
            if ($rateChanged && $term->requiresRateApproval() && (blank($term->regulatory_approval_reference ?? $term->umra_interest_approval_reference) || blank($term->regulatory_approved_at ?? $term->umra_interest_approved_at))) {
                throw new \InvalidArgumentException('Interest-rate changes require the approval evidence specified by this lender regulatory profile.');
            }
        });
    }

    public function requiresRateApproval(): bool
    {
        return (bool) ($this->product?->institution?->rate_change_approval_required ?? true);
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}
