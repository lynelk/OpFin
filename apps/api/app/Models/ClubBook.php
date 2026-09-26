<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubBook extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'policy' => 'array', 'cutover_date' => 'immutable_date', 'closed_through' => 'immutable_date',
            'initial_unit_price_minor' => 'integer', 'unit_scale' => 'integer',
            'policy_version' => 'integer', 'journal_sequence' => 'integer',
        ];
    }
}
