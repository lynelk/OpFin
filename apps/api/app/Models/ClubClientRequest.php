<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ClubClientRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['envelope' => 'encrypted:array', 'resource_id' => 'integer', 'acknowledged_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            foreach (['book_id', 'user_id', 'purpose', 'idempotency_key', 'envelope', 'content_hash', 'reference'] as $field) {
                if ($record->isDirty($field)) {
                    throw new LogicException('A saved accounting request cannot change its meaning.');
                }
            }
        });
    }
}
