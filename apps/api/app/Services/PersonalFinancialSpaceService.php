<?php

namespace App\Services;

use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PersonalFinancialSpaceService
{
    public function ensure(User $user, string $country = 'UG', string $currency = 'UGX'): FinancialSpace
    {
        return DB::transaction(function () use ($user, $country, $currency) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();

            $existing = $this->find($user);
            if ($existing) {
                return $existing;
            }

            $space = FinancialSpace::query()->create([
                'public_id' => (string) Str::uuid(),
                'type' => 'personal',
                'name' => 'My Money',
                'institution_id' => null,
                'country' => strtoupper($country),
                'currency' => strtoupper($currency),
                'status' => 'active',
                'metadata' => [
                    'canonical_personal_space' => true,
                    'provisioned_by' => 'personal_financial_space_service',
                ],
            ]);

            FinancialSpaceMembership::query()->create([
                'financial_space_id' => $space->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'approved_at' => now(),
                'metadata' => ['canonical_personal_membership' => true],
            ]);

            return $space;
        }, 3);
    }

    public function find(User $user): ?FinancialSpace
    {
        return FinancialSpace::query()
            ->select('financial_spaces.*')
            ->join(
                'financial_space_memberships',
                'financial_space_memberships.financial_space_id',
                '=',
                'financial_spaces.id'
            )
            ->where('financial_space_memberships.user_id', $user->id)
            ->where('financial_space_memberships.status', 'active')
            ->whereNull('financial_space_memberships.deleted_at')
            ->where('financial_spaces.type', 'personal')
            ->where('financial_spaces.status', 'active')
            ->whereNull('financial_spaces.deleted_at')
            ->orderBy('financial_spaces.id')
            ->first();
    }
}
