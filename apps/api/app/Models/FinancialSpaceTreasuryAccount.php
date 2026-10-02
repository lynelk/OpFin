<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class FinancialSpaceTreasuryAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id', 'financial_space_id', 'account_name', 'account_type', 'institution_name',
        'account_reference_masked', 'currency', 'opening_balance_minor', 'current_balance_minor',
        'status', 'balance_as_of', 'current_balance_as_of', 'metadata',
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

    /**
     * The opening-balance baseline is immutable from creation. Corrections
     * require a separately reviewed accounting adjustment.
     */
    public function setOpeningBalanceMinorAttribute(mixed $value): void
    {
        $next = (int) $value;
        $current = array_key_exists('opening_balance_minor', $this->attributes)
            ? (int) $this->attributes['opening_balance_minor']
            : null;

        if ($this->exists && $current !== null && $current !== $next) {
            throw new LogicException('Opening balance is locked from account creation; use a reviewed accounting correction.');
        }

        $this->attributes['opening_balance_minor'] = $next;
    }

    /**
     * The opening-balance date is fixed when the account is created.
     * Current balance freshness is tracked separately by current_balance_as_of.
     */
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
