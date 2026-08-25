<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProtectionClaim extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PARTNER_REVIEW = 'partner_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_PAID = 'paid';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'protection_policy_id',
        'user_id',
        'institution_id',
        'claim_reference',
        'partner_claim_reference',
        'status',
        'incident_date',
        'category',
        'description',
        'claimed_amount_minor',
        'approved_amount_minor',
        'evidence',
        'decision_reason',
        'submitted_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'claimed_amount_minor' => 'integer',
            'approved_amount_minor' => 'integer',
            'evidence' => 'array',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function policy()
    {
        return $this->belongsTo(ProtectionPolicy::class, 'protection_policy_id');
    }
}
