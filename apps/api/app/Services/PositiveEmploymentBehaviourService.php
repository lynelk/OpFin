<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PositiveEmploymentBehaviourService
{
    public function assess(User $user): array
    {
        $rules = (array) config('opfin.credit.positive_employment_behaviour.signals', []);
        $maximum = max(0.0, (float) config('opfin.credit.positive_employment_behaviour.max_uplift_points', 5));

        if ($rules === [] || $maximum <= 0) {
            return $this->neutral();
        }

        $signals = DB::table('alternative_data_signals')
            ->where('user_id', $user->id)
            ->where('source_type', 'employer')
            ->whereIn('purpose', ['credit_assessment', 'affordability'])
            ->where('verified', true)
            ->where('risk_eligible', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereIn('signal_key', array_keys($rules))
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->get();

        $seen = [];
        $applied = [];
        $uplift = 0.0;

        foreach ($signals as $signal) {
            $key = (string) $signal->signal_key;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (! $this->isPositive($this->decode($signal->signal_value))) {
                // Negative, inconclusive and missing employer information are neutral by policy.
                continue;
            }

            $points = max(0.0, (float) ($rules[$key] ?? 0));
            if ($points <= 0) {
                continue;
            }

            $remaining = max(0.0, $maximum - $uplift);
            $appliedPoints = min($points, $remaining);
            if ($appliedPoints <= 0) {
                break;
            }

            $uplift += $appliedPoints;
            $applied[] = [
                'signal_key' => $key,
                'points' => round($appliedPoints, 2),
                'provider_reference' => $signal->provider_reference,
                'observed_at' => $signal->observed_at,
            ];

            if ($uplift >= $maximum) {
                break;
            }
        }

        return [
            'uplift_points' => round($uplift, 2),
            'maximum_uplift_points' => $maximum,
            'applied_signals' => $applied,
            'benefit_only' => true,
            'absence_effect' => 'neutral',
            'negative_signal_effect' => 'neutral',
            'reason_codes' => $uplift > 0 ? ['POSITIVE_EMPLOYMENT_BEHAVIOUR_UPLIFT'] : [],
        ];
    }

    private function neutral(): array
    {
        return [
            'uplift_points' => 0.0,
            'maximum_uplift_points' => max(0.0, (float) config('opfin.credit.positive_employment_behaviour.max_uplift_points', 5)),
            'applied_signals' => [],
            'benefit_only' => true,
            'absence_effect' => 'neutral',
            'negative_signal_effect' => 'neutral',
            'reason_codes' => [],
        ];
    }

    private function decode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function isPositive(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(trim($value)),
                ['positive', 'good', 'strong', 'reliable', 'excellent', 'yes', 'true', 'recognised', 'recognized', 'progressing'],
                true,
            );
        }

        if (! is_array($value)) {
            return false;
        }

        foreach (['positive', 'verified_positive', 'beneficial'] as $key) {
            if (array_key_exists($key, $value) && $value[$key] === true) {
                return true;
            }
        }

        foreach (['score', 'rating', 'value'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key]) && (float) $value[$key] > 0) {
                return true;
            }
        }

        foreach (['status', 'assessment', 'rating'] as $key) {
            if (isset($value[$key]) && is_string($value[$key]) && $this->isPositive($value[$key])) {
                return true;
            }
        }

        return false;
    }
}
