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

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}
