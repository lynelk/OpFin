<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetPassport extends Model
{
    protected $fillable = [
        'reference', 'financial_space_id', 'owner_user_id', 'asset_class', 'asset_subclass',
        'make', 'model', 'sku', 'external_identifier_hash', 'identifier_evidence', 'ownership_evidence',
        'valuation', 'status', 'registered_by', 'idempotency_key', 'instruction_hash', 'review_reason',
        'verified_by', 'verified_at', 'supplier_profile_id', 'purchase',
    ];

    protected function casts(): array
    {
        return [
            'identifier_evidence' => 'array',
            'ownership_evidence' => 'array',
            'valuation' => 'array',
            'purchase' => 'array',
            'verified_at' => 'datetime',
        ];
    }
}
