<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialSpaceTreasuryAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id', 'financial_space_id', 'account_name', 'account_type', 'institution_name',
        'account_reference_masked', 'currency', 'opening_balance_minor', 'current_balance_minor',
        'status', 'balance_as_of', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance_minor' => 'integer',
            'current_balance_minor' => 'integer',
            'balance_as_of' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * The opening-balance baseline is fixed when the account is created.
     *
     * Existing balance refresh callers also supply today's balance_as_of.
     * That refresh timestamp must never replace the opening baseline used by
     * historical transaction, import and statement validation. Recalculation
     * remains observable through updated_at. Rebaselining requires a separate
     * reviewed accounting correction, not mass assignment to this attribute.
     */
    public function setOpeningBalanceMinorAttribute(mixed $value): void
    {
        if ($this->exists && $this->transactions()->exists()) {
            throw new \LogicException('Opening balance is locked after the first cashbook transaction.');
        }

        $this->attributes['opening_balance_minor'] = (int) $value;
    }

    public function setBalanceAsOfAttribute(mixed $value): void
    {
        if ($this->exists) {
            return;
        }

        $this->attributes['balance_as_of'] = $value === null || $value === ''
            ? null
            : $this->asDateTime($value)->format('Y-m-d');
    }

    public function space()
    {
        return $this->belongsTo(FinancialSpace::class, 'financial_space_id');
    }

    public function transactions()
    {
        return $this->hasMany(FinancialSpaceTransaction::class, 'treasury_account_id');
    }

    public function imports()
    {
        return $this->hasMany(FinancialSpaceStatementImport::class, 'treasury_account_id');
    }
}
