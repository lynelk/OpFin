<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityFinanceScorecard extends Model
{
    protected $fillable = [
        'user_id', 'community_finance_programme_id', 'community_finance_membership_id', 'score_name',
        'composite_score', 'band', 'confidence_percent', 'component_scores', 'factor_breakdown',
        'explanation', 'policy_version', 'generated_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'composite_score' => 'integer',
            'confidence_percent' => 'integer',
            'component_scores' => 'array',
            'factor_breakdown' => 'array',
            'explanation' => 'array',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
