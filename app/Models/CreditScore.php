<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditScore extends Model
{
    protected $fillable = [
        'user_id',
        'score',
        'band',
        'rating',
        'probability_of_default_percent',
        'likelihood_to_default',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'probability_of_default_percent' => 'float',
    ];
}
