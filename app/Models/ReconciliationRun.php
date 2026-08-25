<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationRun extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = ['provider', 'business_date', 'status', 'created_by', 'started_at', 'completed_at', 'summary'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    public function items()
    {
        return $this->hasMany(ReconciliationItem::class);
    }

    public function providerRecords()
    {
        return $this->hasMany(ProviderStatementRecord::class);
    }
}
