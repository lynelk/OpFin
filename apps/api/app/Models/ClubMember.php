<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubMember extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['units_micro' => 'integer', 'capital_minor' => 'integer'];
    }
}
