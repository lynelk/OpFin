<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalMandate extends Model
{
    protected $fillable = [
        'reference',
        'owner_user_id',
        'mandate_type',
        'name',
        'committed_capital_minor',
        'deployed_capital_minor',
        'reserved_capital_minor',
        'status',
        'investment_policy',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'committed_capital_minor' => 'integer',
            'deployed_capital_minor' => 'integer',
            'reserved_capital_minor' => 'integer',
            'investment_policy' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function offers()
    {
        return $this->hasMany(CreditOffer::class, 'funding_pool_id');
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'funding_pool_id');
    }
}
