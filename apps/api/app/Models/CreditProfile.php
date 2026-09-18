<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditProfile extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_PROVISIONAL = 'provisional';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'user_id',
        'status',
        'composite_score',
        'band',
        'coverage_percent',
        'credit_limit_minor',
        'current_exposure_minor',
        'available_to_borrow_minor',
        'amount_due_minor',
        'total_outstanding_minor',
        'next_due_date',
        'model_version',
        'component_breakdown',
        'reason_codes',
        'customer_explanations',
        'scored_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'composite_score' => 'float',
            'coverage_percent' => 'float',
            'credit_limit_minor' => 'integer',
            'current_exposure_minor' => 'integer',
            'available_to_borrow_minor' => 'integer',
            'amount_due_minor' => 'integer',
            'total_outstanding_minor' => 'integer',
            'next_due_date' => 'date',
            'component_breakdown' => 'array',
            'reason_codes' => 'array',
            'customer_explanations' => 'array',
            'scored_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
