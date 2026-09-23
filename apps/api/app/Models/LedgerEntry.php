<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    public const DIRECTION_DEBIT = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    protected $fillable = [
        'ledger_transaction_id',
        'ledger_account_id',
        'direction',
        'amount_minor',
        'currency',
        'memo',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Ledger entries are immutable; post an append-only correction instead.'));
        static::deleting(fn () => throw new \LogicException('Ledger entries are immutable and cannot be deleted.'));
    }
}
