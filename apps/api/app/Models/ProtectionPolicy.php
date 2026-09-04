<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProtectionPolicy extends Model
{
    public const STATUS_PREMIUM_DUE = 'premium_due';

    public const STATUS_PREMIUM_PENDING = 'premium_pending';

    public const STATUS_PENDING_ISSUANCE = 'pending_issuance';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_LAPSED = 'lapsed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'protection_product_id',
        'user_id',
        'institution_id',
        'policy_reference',
        'external_policy_number',
        'partner_reference',
        'status',
        'premium_amount_minor',
        'premium_frequency',
        'coverage_limit_minor',
        'cover_start_date',
        'cover_end_date',
        'next_premium_due_date',
        'disclosure_hash',
        'acceptance_metadata',
        'enrolled_at',
        'issued_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'premium_amount_minor' => 'integer',
            'coverage_limit_minor' => 'integer',
            'cover_start_date' => 'date',
            'cover_end_date' => 'date',
            'next_premium_due_date' => 'date',
            'acceptance_metadata' => 'array',
            'enrolled_at' => 'datetime',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(ProtectionProduct::class, 'protection_product_id');
    }

    public function premiumPayments()
    {
        return $this->hasMany(ProtectionPremiumPayment::class);
    }

    public function claims()
    {
        return $this->hasMany(ProtectionClaim::class);
    }
}
