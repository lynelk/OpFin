<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'balance',
        'loan_product_id',
    ];

    // get jounal entries for the account
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    // soft delete the journal entries when account is deleted
    protected static function booted()
    {
        static::deleting(function ($account) {
            if (!$account->isForceDeleting()) {
                $account->journalEntries->each->delete();
            }
        });
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }
}
