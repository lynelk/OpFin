<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocationContext extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'subject_type',
        'subject_id',
        'financial_space_id',
        'user_id',
        'created_by_user_id',
        'purpose',
        'source',
        'precision_level',
        'place_name',
        'formatted_address',
        'google_place_id',
        'plus_code',
        'country_code',
        'admin_area_1',
        'admin_area_2',
        'locality',
        'latitude',
        'longitude',
        'accuracy_metres',
        'consent_purpose',
        'verification_status',
        'captured_at',
        'verified_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_metres' => 'integer',
            'captured_at' => 'datetime',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function financialSpace()
    {
        return $this->belongsTo(FinancialSpace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
