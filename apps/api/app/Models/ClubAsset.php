<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubAsset extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity_micro' => 'integer', 'cost_minor' => 'integer', 'carrying_value_minor' => 'integer', 'valuation_date' => 'immutable_date'];
    }
}
