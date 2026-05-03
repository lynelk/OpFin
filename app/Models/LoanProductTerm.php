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
    ];

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}
