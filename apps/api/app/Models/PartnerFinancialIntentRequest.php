<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerFinancialIntentRequest extends Model
{
    protected $fillable = [
        'reference', 'partner_account_id', 'customer_user_id', 'source_platform', 'external_reference',
        'idempotency_key', 'need_type', 'amount_minor', 'currency', 'purpose', 'customer_consent_reference',
        'status', 'confirmed_financial_intent_id', 'confirmed_financial_space_id', 'confirmed_at', 'declined_at',
        'expires_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'purpose' => 'array',
            'metadata' => 'array',
            'confirmed_at' => 'datetime',
            'declined_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function financialIntent()
    {
        return $this->belongsTo(FinancialIntent::class, 'confirmed_financial_intent_id');
    }
}
