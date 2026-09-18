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
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:6',
            'default_interest_rate' => 'decimal:6',
            'umra_interest_approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (LoanProductTerm $term) {
            $rateChanged = $term->isDirty('interest_rate') || $term->isDirty('default_interest_rate');
            if ($rateChanged && (blank($term->umra_interest_approval_reference) || blank($term->umra_interest_approved_at))) {
                throw new \InvalidArgumentException('Interest-rate changes require recorded prior written UMRA approval.');
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}
