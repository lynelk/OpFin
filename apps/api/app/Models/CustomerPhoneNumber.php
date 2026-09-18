<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPhoneNumber extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'kind',
        'provider',
        'ownership_name_match_status',
        'sim_tenure_days',
        'verified_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sim_tenure_days' => 'integer',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallets()
    {
        return $this->hasMany(CustomerWallet::class, 'phone_number_id');
    }
}
