<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialSpaceTreasuryAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id','financial_space_id','account_name','account_type','institution_name',
        'account_reference_masked','currency','opening_balance_minor','current_balance_minor',
        'status','balance_as_of','current_balance_as_of','metadata',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance_minor' => 'integer',
            'current_balance_minor' => 'integer',
            'balance_as_of' => 'date',
            'current_balance_as_of' => 'date',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (FinancialSpaceTreasuryAccount $account) {
            if (! $account->transactions()->exists()) {
                return;
            }

            foreach (['financial_space_id', 'currency', 'opening_balance_minor', 'balance_as_of'] as $field) {
                if ($account->isDirty($field)) {
                    throw new \LogicException(
                        'Treasury account opening economics are locked after the first cashbook transaction. Post a correction instead.'
                    );
                }
            }
        });
    }

    public function space() { return $this->belongsTo(FinancialSpace::class, 'financial_space_id'); }
    public function transactions() { return $this->hasMany(FinancialSpaceTransaction::class, 'treasury_account_id'); }
    public function imports() { return $this->hasMany(FinancialSpaceStatementImport::class, 'treasury_account_id'); }
}
