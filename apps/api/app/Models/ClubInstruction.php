<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ClubInstruction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'result' => 'array', 'business_date' => 'immutable_date', 'decided_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $instruction): void {
            foreach (['book_id', 'type', 'business_date', 'payload', 'payload_hash', 'maker_id', 'idempotency_key', 'reference'] as $field) {
                if ($instruction->isDirty($field)) {
                    throw new LogicException('Submitted accounting instructions are immutable. Cancel and create a new instruction.');
                }
            }
            if ($instruction->getOriginal('status') !== 'pending') {
                throw new LogicException('A decided instruction cannot be edited. Corrections require a new approved instruction.');
            }
        });
        static::deleting(static function (): void {
            throw new LogicException('Accounting instructions must be retained as audit evidence.');
        });
    }
}
