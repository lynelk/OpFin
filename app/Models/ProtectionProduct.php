<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProtectionProduct extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'code',
        'name',
        'insurer_name',
        'underwriter_name',
        'partner_product_reference',
        'country_code',
        'currency',
        'product_type',
        'status',
        'premium_amount_minor',
        'premium_frequency',
        'coverage_limit_minor',
        'disclosure_version',
        'benefits',
        'exclusions',
        'disclosure_payload',
        'terms_url',
        'effective_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'premium_amount_minor' => 'integer',
            'coverage_limit_minor' => 'integer',
            'benefits' => 'array',
            'exclusions' => 'array',
            'disclosure_payload' => 'array',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function policies()
    {
        return $this->hasMany(ProtectionPolicy::class);
    }

    public function disclosureHash(): string
    {
        $payload = [
            'code' => $this->code,
            'name' => $this->name,
            'insurer_name' => $this->insurer_name,
            'underwriter_name' => $this->underwriter_name,
            'currency' => $this->currency,
            'premium_amount_minor' => $this->premium_amount_minor,
            'premium_frequency' => $this->premium_frequency,
            'coverage_limit_minor' => $this->coverage_limit_minor,
            'benefits' => $this->benefits,
            'exclusions' => $this->exclusions,
            'disclosure_version' => $this->disclosure_version,
            'disclosure_payload' => $this->disclosure_payload,
            'terms_url' => $this->terms_url,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
