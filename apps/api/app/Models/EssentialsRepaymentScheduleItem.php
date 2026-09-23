<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssentialsRepaymentScheduleItem extends Model
{
    protected $fillable = [
        'advance_id',
        'installment_number',
        'due_date',
        'principal_original_minor',
        'principal_outstanding_minor',
        'interest_original_minor',
        'interest_outstanding_minor',
        'fees_original_minor',
        'fees_outstanding_minor',
        'total_original_minor',
        'total_outstanding_minor',
        'status',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_original_minor' => 'integer',
            'principal_outstanding_minor' => 'integer',
            'interest_original_minor' => 'integer',
            'interest_outstanding_minor' => 'integer',
            'fees_original_minor' => 'integer',
            'fees_outstanding_minor' => 'integer',
            'total_original_minor' => 'integer',
            'total_outstanding_minor' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    public function advance()
    {
        return $this->belongsTo(EssentialsAdvance::class, 'advance_id');
    }
}
