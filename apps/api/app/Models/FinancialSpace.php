<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialSpace extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id', 'type', 'name', 'institution_id', 'country', 'currency', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function memberships()
    {
        return $this->hasMany(FinancialSpaceMembership::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}
