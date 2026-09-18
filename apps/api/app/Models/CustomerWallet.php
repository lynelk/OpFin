<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerWallet extends Model
{
    protected $fillable = [
        'user_id',
        'phone_number_id',
        'provider',
        'msisdn',
        'status',
        'is_default_disbursement',
        'is_default_repayment',
        'verified_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_default_disbursement' => 'boolean',
            'is_default_repayment' => 'boolean',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function phoneNumber()
    {
        return $this->belongsTo(CustomerPhoneNumber::class, 'phone_number_id');
    }
}
