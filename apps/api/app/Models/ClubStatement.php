<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubStatement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'period_start' => 'immutable_date', 'period_end' => 'immutable_date'];
    }
}
