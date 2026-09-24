<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EssentialsAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id', 'user_id', 'financial_space_id', 'biller_id', 'account_reference',
        'account_reference_hash', 'nickname', 'verification_status', 'provider_reference',
        'verified_at', 'metadata',
    ];

    protected $hidden = ['account_reference'];

    protected function casts(): array
    {
        return [
            'account_reference' => 'encrypted',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function biller()
    {
        return $this->belongsTo(EssentialsBiller::class, 'biller_id');
    }
}
