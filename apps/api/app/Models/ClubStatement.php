<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ClubStatement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'period_start' => 'immutable_date', 'period_end' => 'immutable_date'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void { throw new LogicException('Issued statements are immutable.'); });
        static::deleting(static function (): void { throw new LogicException('Issued statements are retained.'); });
    }
}
