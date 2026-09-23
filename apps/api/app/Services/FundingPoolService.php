<?php

namespace App\Services;

use App\Models\CreditOffer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class FundingPoolService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    private const BLOCKED_STATUSES = [
        'draft',
        'paused',
        'closed',
        'cancelled',
        'retired',
        'suspended',
    ];

    public function validateSelection(?int $fundingPoolId, int $principalMinor): void
    {
        if ($principalMinor <= 0) {
            throw new InvalidArgumentException('Funding-pool validation requires a positive principal amount.');
        }

        if (! $fundingPoolId) {
            if ((bool) config('opfin.credit.require_funding_pool_assignment', false)) {
                throw new InvalidArgumentException('A funding pool must be assigned before this credit offer can be generated.');
            }

            return;
        }

        $pool = DB::table('capital_mandates')->where('id', $fundingPoolId)->first();
        $this->assertUsable($pool, $principalMinor);
    }

    public function reserve(CreditOffer $offer): CreditOffer
    {
        if (! $offer->funding_pool_id) {
            if ((bool) config('opfin.credit.require_funding_pool_assignment', false)) {
                throw new InvalidArgumentException('A funding pool must be assigned before disbursement can be initiated.');
            }

            return $offer;
        }

        return DB::transaction(function () use ($offer) {
            $lockedOffer = CreditOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($lockedOffer->funding_committed_at || $lockedOffer->funding_reserved_at) {
                return $lockedOffer;
            }
            if ($lockedOffer->funding_released_at || $lockedOffer->funding_reversed_at) {
                throw new RuntimeException('A released or reversed funding allocation cannot be reserved again.');
            }

            $pool = DB::table('capital_mandates')->where('id', $lockedOffer->funding_pool_id)->lockForUpdate()->first();
            $this->assertUsable($pool, (int) $lockedOffer->principal_amount_minor);

            DB::table('capital_mandates')->where('id', $pool->id)->update([
                'reserved_capital_minor' => (int) $pool->reserved_capital_minor + (int) $lockedOffer->principal_amount_minor,
                'updated_at' => now(),
            ]);

            $lockedOffer->forceFill(['funding_reserved_at' => now()])->save();
            $this->auditLogger->record('funding_pool.capital_reserved', null, $lockedOffer, [
                'funding_pool_id' => $pool->id,
                'principal_minor' => (int) $lockedOffer->principal_amount_minor,
            ]);

            return $lockedOffer->fresh();
        });
    }

    public function commit(CreditOffer $offer): CreditOffer
    {
        if (! $offer->funding_pool_id) {
            return $offer;
        }

        return DB::transaction(function () use ($offer) {
            $lockedOffer = CreditOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($lockedOffer->funding_committed_at) {
                return $lockedOffer;
            }
            if (! $lockedOffer->funding_reserved_at || $lockedOffer->funding_released_at) {
                throw new RuntimeException('Funding must be reserved before it can be committed.');
            }

            $principal = (int) $lockedOffer->principal_amount_minor;
            $pool = DB::table('capital_mandates')->where('id', $lockedOffer->funding_pool_id)->lockForUpdate()->first();
            if (! $pool) {
                throw new RuntimeException('The assigned funding pool no longer exists.');
            }
            if ((int) $pool->reserved_capital_minor < $principal) {
                throw new RuntimeException('Funding-pool reserved capital is lower than the offer principal; financial integrity review is required.');
            }

            DB::table('capital_mandates')->where('id', $pool->id)->update([
                'reserved_capital_minor' => (int) $pool->reserved_capital_minor - $principal,
                'deployed_capital_minor' => (int) $pool->deployed_capital_minor + $principal,
                'updated_at' => now(),
            ]);

            $lockedOffer->forceFill(['funding_committed_at' => now()])->save();
            $this->auditLogger->record('funding_pool.capital_deployed', null, $lockedOffer, [
                'funding_pool_id' => $pool->id,
                'principal_minor' => $principal,
            ]);

            return $lockedOffer->fresh();
        });
    }

    public function release(CreditOffer $offer): CreditOffer
    {
        if (! $offer->funding_pool_id) {
            return $offer;
        }

        return DB::transaction(function () use ($offer) {
            $lockedOffer = CreditOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($lockedOffer->funding_committed_at || $lockedOffer->funding_released_at || ! $lockedOffer->funding_reserved_at) {
                return $lockedOffer;
            }

            $principal = (int) $lockedOffer->principal_amount_minor;
            $pool = DB::table('capital_mandates')->where('id', $lockedOffer->funding_pool_id)->lockForUpdate()->first();
            if (! $pool) {
                throw new RuntimeException('The assigned funding pool no longer exists.');
            }
            if ((int) $pool->reserved_capital_minor < $principal) {
                throw new RuntimeException('Funding-pool reserved capital is lower than the offer principal; release is blocked for review.');
            }

            DB::table('capital_mandates')->where('id', $pool->id)->update([
                'reserved_capital_minor' => (int) $pool->reserved_capital_minor - $principal,
                'updated_at' => now(),
            ]);

            $lockedOffer->forceFill(['funding_released_at' => now()])->save();
            $this->auditLogger->record('funding_pool.capital_released', null, $lockedOffer, [
                'funding_pool_id' => $pool->id,
                'principal_minor' => $principal,
            ]);

            return $lockedOffer->fresh();
        });
    }

    public function reverseCommitted(CreditOffer $offer): CreditOffer
    {
        if (! $offer->funding_pool_id) {
            return $offer;
        }

        return DB::transaction(function () use ($offer) {
            $lockedOffer = CreditOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($lockedOffer->funding_reversed_at) {
                return $lockedOffer;
            }
            if (! $lockedOffer->funding_committed_at) {
                if (! $lockedOffer->funding_reserved_at || $lockedOffer->funding_released_at) {
                    return $lockedOffer;
                }

                $principal = (int) $lockedOffer->principal_amount_minor;
                $pool = DB::table('capital_mandates')->where('id', $lockedOffer->funding_pool_id)->lockForUpdate()->first();
                if (! $pool || (int) $pool->reserved_capital_minor < $principal) {
                    throw new RuntimeException('Funding reservation cannot be reversed automatically; financial integrity review is required.');
                }

                DB::table('capital_mandates')->where('id', $pool->id)->update([
                    'reserved_capital_minor' => (int) $pool->reserved_capital_minor - $principal,
                    'updated_at' => now(),
                ]);
                $lockedOffer->forceFill(['funding_released_at' => now(), 'funding_reversed_at' => now()])->save();
                $this->auditLogger->record('funding_pool.reservation_reversed', null, $lockedOffer, [
                    'funding_pool_id' => $pool->id,
                    'principal_minor' => $principal,
                ]);

                return $lockedOffer->fresh();
            }

            $principal = (int) $lockedOffer->principal_amount_minor;
            $pool = DB::table('capital_mandates')->where('id', $lockedOffer->funding_pool_id)->lockForUpdate()->first();
            if (! $pool) {
                throw new RuntimeException('The assigned funding pool no longer exists.');
            }
            if ((int) $pool->deployed_capital_minor < $principal) {
                throw new RuntimeException('Funding-pool deployed capital is lower than the reversed principal; reversal is blocked for review.');
            }

            DB::table('capital_mandates')->where('id', $pool->id)->update([
                'deployed_capital_minor' => (int) $pool->deployed_capital_minor - $principal,
                'updated_at' => now(),
            ]);

            $lockedOffer->forceFill(['funding_reversed_at' => now()])->save();
            $this->auditLogger->record('funding_pool.deployment_reversed', null, $lockedOffer, [
                'funding_pool_id' => $pool->id,
                'principal_minor' => $principal,
            ]);

            return $lockedOffer->fresh();
        });
    }

    private function assertUsable(?object $pool, int $principalMinor): void
    {
        if (! $pool) {
            throw new InvalidArgumentException('The selected funding pool does not exist.');
        }
        if (! $pool->approved_by || ! $pool->approved_at) {
            throw new InvalidArgumentException('The selected funding pool has not completed approval.');
        }
        if (in_array(strtolower((string) $pool->status), self::BLOCKED_STATUSES, true)) {
            throw new InvalidArgumentException('The selected funding pool is not available for new credit.');
        }

        $available = (int) $pool->committed_capital_minor
            - (int) $pool->deployed_capital_minor
            - (int) ($pool->reserved_capital_minor ?? 0);

        if ($available < $principalMinor) {
            throw new InvalidArgumentException('The selected funding pool does not have enough unreserved capital for this offer.');
        }
    }
}
