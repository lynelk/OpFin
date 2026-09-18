<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditTermVariation extends Model
{
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_UMRA_APPROVED = 'umra_approved';
    public const STATUS_CUSTOMER_CONSENTED = 'customer_consented';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'loan_id',
        'credit_offer_id',
        'proposed_by',
        'status',
        'proposed_changes',
        'reason',
        'requires_umra_approval',
        'umra_approval_reference',
        'umra_approval_document_hash',
        'umra_approved_at',
        'customer_consent_hash',
        'customer_consent_metadata',
        'customer_consented_at',
        'effective_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_changes' => 'array',
            'requires_umra_approval' => 'boolean',
            'umra_approved_at' => 'datetime',
            'customer_consent_metadata' => 'array',
            'customer_consented_at' => 'datetime',
            'effective_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }
}
