<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class EssentialsCollectionInstruction extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['snapshot', 'route_hash', 'merchant_scope'];

    protected function casts(): array
    {
        return ['snapshot' => 'encrypted:array', 'amount_minor' => 'integer',
            'submitted_at' => 'immutable_datetime', 'observed_at' => 'immutable_datetime', 'applied_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $instruction): void {
            if (DB::transactionLevel() < 1) {
                throw new LogicException('Collection preparation requires a locked financial transaction.');
            }
            if (self::where('advance_id', $instruction->advance_id)
                ->whereNotIn('status', ['applied', 'failed', 'reversed'])->lockForUpdate()->first(['id'])) {
                throw new InvalidArgumentException('A repayment collection is already awaiting provider confirmation for this advance.');
            }
        });
        static::updating(function (self $instruction): void {
            foreach (['repayment_id', 'advance_id', 'user_id', 'wallet_id', 'amount_minor', 'currency',
                'environment', 'merchant_scope', 'instruction_hash', 'route_hash', 'snapshot'] as $field) {
                if ($instruction->isDirty($field)) {
                    throw new LogicException('A submitted collection cannot change its economic identity.');
                }
            }
        });
        static::deleting(static function (): void {
            throw new LogicException('Collection instructions must be retained as financial evidence.');
        });
    }
}
