<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'partner_id', 'code', 'name', 'product_type', 'status', 'country', 'currency',
        'eligibility_rules', 'pricing', 'disclosures', 'integration_config',
    ];

    protected function casts(): array
    {
        return [
            'eligibility_rules' => 'array',
            'pricing' => 'array',
            'disclosures' => 'array',
            'integration_config' => 'array',
        ];
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
