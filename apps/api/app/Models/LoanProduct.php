<?php

namespace App\Models;

use App\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'status',
        'institution_id',
        'country', 'currency', 'product_category', 'min_amount_minor', 'max_amount_minor', 'borrower_purposes', 'funding_pool_id',
    ];

    protected function casts(): array
    {
        return ['borrower_purposes' => 'array', 'min_amount_minor' => 'integer', 'max_amount_minor' => 'integer', 'funding_pool_id' => 'integer'];
    }

    public function terms()
    {
        return $this->hasMany(LoanProductTerm::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new InstitutionScope);
    }

    protected static function booted()
    {
        static::created(function ($loanProduct) {
            Account::create([
                'loan_product_id' => $loanProduct->id,
                'name' => $loanProduct->name,
            ]);
            Account::create([
                'name' => 'Interest Income',
                'loan_product_id' => $loanProduct->id,
            ]);
        });

        static::deleting(function ($loanProduct) {
            if (! $loanProduct->isForceDeleting()) {
                $loanProduct->terms->each->delete();
                $loanProduct->account->delete();
            }
        });
    }

    public function account()
    {
        return $this->hasOne(Account::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}
