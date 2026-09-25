<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubInstruction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'result' => 'array', 'business_date' => 'immutable_date', 'decided_at' => 'immutable_datetime'];
    }
}
