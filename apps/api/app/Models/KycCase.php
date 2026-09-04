<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycCase extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'reviewed_by',
        'provider',
        'provider_reference',
        'national_id',
        'status',
        'evidence',
        'risk_flags',
        'review_notes',
        'submitted_at',
        'reviewed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'risk_flags' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
