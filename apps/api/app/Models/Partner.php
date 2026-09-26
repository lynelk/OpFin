<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'institution_id', 'code', 'name', 'partner_type', 'country', 'status',
        'adapter_key', 'regulatory_evidence', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'regulatory_evidence' => 'array',
            'metadata' => 'array',
        ];
    }

    public function products()
    {
        return $this->hasMany(PartnerProduct::class);
    }
}
