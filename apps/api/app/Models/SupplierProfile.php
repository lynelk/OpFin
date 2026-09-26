<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierProfile extends Model
{
    protected $fillable = [
        'reference', 'partner_id', 'name', 'country', 'verification_status', 'kyb_evidence', 'settlement_details',
    ];

    protected function casts(): array
    {
        return ['kyb_evidence' => 'array', 'settlement_details' => 'array'];
    }
}
