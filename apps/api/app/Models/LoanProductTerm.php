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
        'guarantors_required',
        'umra_interest_approval_reference',
        'umra_interest_approval_document_hash',
        'umra_interest_approved_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'guarantors_required' => 'integer',
            'umra_interest_approved_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}
