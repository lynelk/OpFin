<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class IdentityVerificationReceipt extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['lookup_hash', 'receipt_key', 'provider_reference', 'result'];

    protected function casts(): array
    {
        return [
            'provider_reference' => 'encrypted',
            'result' => 'encrypted:array',
            'verified_at' => 'immutable_datetime',
            'refresh_after' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'retention_until' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $receipt): void {
            $allowed = ['revoked_at', 'updated_at'];
            if (array_diff(array_keys($receipt->getDirty()), $allowed) !== []) {
                throw new LogicException('Verification observations are immutable; record a new provider result.');
            }
        });
    }
}
