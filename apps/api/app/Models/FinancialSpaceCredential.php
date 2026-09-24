<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialSpaceCredential extends Model
{
    protected $fillable = [
        'financial_space_id',
        'credential_type',
        'issuer_code',
        'issuer_name',
        'credential_value',
        'jurisdiction_country',
        'verification_status',
        'issued_at',
        'expires_at',
        'verified_at',
        'verified_by_user_id',
        'verification_reference',
        'verification_evidence_hash',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function financialSpace()
    {
        return $this->belongsTo(FinancialSpace::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
