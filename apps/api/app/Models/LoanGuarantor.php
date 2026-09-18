<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanGuarantor extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';

    protected $fillable = [
        'loan_application_id',
        'borrower_user_id',
        'position',
        'phone',
        'name',
        'status',
        'verification_method',
        'verification_reference',
        'consent_evidence',
        'verified_at',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'consent_evidence' => 'array',
            'verified_at' => 'datetime',
            'consented_at' => 'datetime',
        ];
    }
}
