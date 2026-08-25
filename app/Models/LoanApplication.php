<?php

namespace App\Models;

use App\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanApplication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'loan_product_id',
        'loan_product_term_id',
        'institution_id',
        'amount',
        'status',
        'reason',
        'disbursed_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new InstitutionScope);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function loanProductTerm()
    {
        return $this->belongsTo(LoanProductTerm::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function loan()
    {
        return $this->hasOne(Loan::class);
    }

    public function creditDecision()
    {
        return $this->hasOne(CreditDecision::class);
    }

    public function creditOffers()
    {
        return $this->hasMany(CreditOffer::class);
    }

    public function demoDecision()
    {
        return $this->hasOne(DemoLoanDecision::class);
    }

    public function demoOffer()
    {
        return $this->hasOne(DemoLoanOffer::class);
    }
}
