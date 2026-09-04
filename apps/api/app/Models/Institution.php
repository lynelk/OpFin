<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'address', 'phone', 'email', 'status'];

    public function loanProducts()
    {
        return $this->hasMany(LoanProduct::class);
    }

    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function loanSchedules()
    {
        return $this->hasMany(LoanSchedule::class);
    }

    protected static function booted()
    {
        static::deleting(function ($institution) {
            if (!$institution->isForceDeleting()) {
                $institution->loanApplications->each->delete();
                $institution->loanProducts->each->delete();
                $institution->users->each->delete();
                $institution->transactions->each->delete();
                $institution->loanSchedules->each->delete();
            }
        });
    }
}
