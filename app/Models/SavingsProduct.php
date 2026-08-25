<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsProduct extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'code',
        'name',
        'partner_name',
        'partner_product_reference',
        'country_code',
        'currency',
        'product_type',
        'status',
        'custody_model',
        'minimum_contribution_minor',
        'maximum_contribution_minor',
        'minimum_withdrawal_minor',
        'notice_days',
        'lock_days',
        'terms_version',
        'terms_url',
        'disclosures',
        'effective_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'minimum_contribution_minor' => 'integer',
            'maximum_contribution_minor' => 'integer',
            'minimum_withdrawal_minor' => 'integer',
            'notice_days' => 'integer',
            'lock_days' => 'integer',
            'disclosures' => 'array',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function goals()
    {
        return $this->hasMany(SavingsGoal::class);
    }
}
