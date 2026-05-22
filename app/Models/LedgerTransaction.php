<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerTransaction extends Model
{
    protected $fillable = [
        'reference',
        'event_type',
        'currency',
        'source_type',
        'source_id',
        'posted_by',
        'posted_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
