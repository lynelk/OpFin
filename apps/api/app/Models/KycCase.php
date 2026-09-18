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
        'national_id_front_path',
        'national_id_back_path',
        'selfie_with_id_path',
        'liveness_status',
        'face_match_status',
        'nin_phone_link_status',
        'evidence_complete_at',
        'status',
        'evidence',
        'risk_flags',
        'review_notes',
        'submitted_at',
        'reviewed_at',
        'expires_at',
    ];

    protected $hidden = [
        'national_id_front_path',
        'national_id_back_path',
        'selfie_with_id_path',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'risk_flags' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
            'evidence_complete_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
