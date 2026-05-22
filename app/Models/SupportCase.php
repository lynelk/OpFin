<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCase extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'customer_id',
        'assigned_to',
        'created_by',
        'case_number',
        'category',
        'status',
        'priority',
        'subject',
        'description',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function notes()
    {
        return $this->hasMany(SupportCaseNote::class);
    }
}
