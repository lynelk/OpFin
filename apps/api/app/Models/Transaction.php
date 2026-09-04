<?php

namespace App\Models;

use App\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'institution_id',
        'loan_application_id',
        'loan_id',
        'type',
        'amount',
        'phone',
        'reference',
        'external_reference',
        'network_reference',
        'status',
        'data',
    ];

    /**
     * Get the loan application that owns the transaction.
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    /**
     * Get the loan that owns the transaction.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the institution that owns the transaction.
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new InstitutionScope);
        static::creating(function ($transaction) {
            $phone = $transaction->phone;

            if (str_starts_with($phone, '25676') || str_starts_with($phone, '25677') || str_starts_with($phone, '25678')) {
                $transaction->network = 'MTN';
            } elseif (str_starts_with($phone, '25670') || str_starts_with($phone, '25674') || str_starts_with($phone, '25675')) {
                $transaction->network = 'AIRTEL';
            } else {
                $transaction->network = 'UNKNOWN';
            }
        });
    }
}
