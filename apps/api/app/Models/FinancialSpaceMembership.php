<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialSpaceMembership extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'financial_space_id', 'user_id', 'role', 'status', 'joined_at', 'approved_at', 'suspended_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function space()
    {
        return $this->belongsTo(FinancialSpace::class, 'financial_space_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
