<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AccountDataDeletionService
{
    private const CATEGORIES = [
        'location_context' => [
            'label' => 'Saved location context',
            'description' => 'Optional saved places and task-specific location context.',
        ],
        'financial_planning' => [
            'label' => 'Personal planning data',
            'description' => 'Optional budgets, planning accounts, entries and calendar items.',
        ],
        'linked_accounts' => [
            'label' => 'Linked account connections',
            'description' => 'Optional external account links and synchronisation context.',
        ],
        'household_and_microbusiness' => [
            'label' => 'Household and microbusiness profile data',
            'description' => 'Optional household and microbusiness planning profiles.',
        ],
        'profile_preferences' => [
            'label' => 'Profile preferences',
            'description' => 'Optional accessibility and language preferences. Language resets to English.',
        ],
    ];

    public const RETAINED = [
        'financial transaction and settlement evidence where legally required',
        'loan, repayment, accounting and reconciliation records where legally required',
        'KYC/AML, credit-reporting and regulatory evidence where legally required',
        'security, consent and audit evidence required to demonstrate lawful processing and account closure',
        'financial-space, community-finance and offline-sync evidence where needed for financial, security or dispute traceability',
    ];

    public function options(): array
    {
        return collect(self::CATEGORIES)
            ->map(fn (array $definition, string $code) => [
                'code' => $code,
                ...$definition,
            ])
            ->values()
            ->all();
    }

    public function delete(User $user, array $categories): array
    {
        $categories = array_values(array_unique(array_map('strval', $categories)));
        $unknown = array_values(array_diff($categories, array_keys(self::CATEGORIES)));

        if ($categories === [] || $unknown !== []) {
            throw ValidationException::withMessages([
                'data_categories' => [
                    $unknown === []
                        ? 'Select at least one supported data category.'
                        : 'One or more selected data categories are not supported.',
                ],
            ]);
        }

        $deleted = [];

        if (in_array('location_context', $categories, true)) {
            $deleted['location_context'] = $this->deleteLocation($user->id);
        }

        if (in_array('financial_planning', $categories, true)) {
            $deleted['financial_planning'] =
                $this->deleteUserRows('financial_budgets', $user->id)
                + $this->deleteUserRows('financial_entries', $user->id)
                + $this->deleteUserRows('financial_calendar_events', $user->id)
                + $this->deleteUserRows('financial_accounts', $user->id);
        }

        if (in_array('linked_accounts', $categories, true)) {
            $deleted['linked_accounts'] = $this->deleteUserRows('linked_financial_accounts', $user->id);
        }

        if (in_array('household_and_microbusiness', $categories, true)) {
            $deleted['household_and_microbusiness'] =
                $this->deleteUserRows('household_finance_profiles', $user->id)
                + $this->deleteUserRows('microbusiness_profiles', $user->id);
        }

        if (in_array('profile_preferences', $categories, true)) {
            User::withoutGlobalScopes()
                ->whereKey($user->id)
                ->update([
                    'preferred_language' => 'en',
                    'accessibility_preferences' => null,
                    'updated_at' => now(),
                ]);

            $deleted['profile_preferences'] = 1;
        }

        return $deleted;
    }

    public function purgeForClosedAccount(int $userId): void
    {
        // Closing an account must not erase regulated or auditable financial evidence.
        // The user record is de-identified and soft-deleted by AccountDeletionService,
        // so retained records remain inaccessible to the former customer session while
        // preserving the evidence chain required for accounting, credit, settlement,
        // security, disputes and regulatory reporting.
        foreach ([
            'financial_accounts',
            'financial_budgets',
            'financial_entries',
            'financial_calendar_events',
            'linked_financial_accounts',
            'household_finance_profiles',
            'microbusiness_profiles',
            'customer_wallets',
            'customer_phone_numbers',
        ] as $table) {
            $this->deleteUserRows($table, $userId);
        }

        $this->deleteLocation($userId);
    }

    private function deleteUserRows(string $table, int $userId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
            return 0;
        }

        return DB::table($table)
            ->where('user_id', $userId)
            ->delete();
    }

    private function deleteLocation(int $userId): int
    {
        if (! Schema::hasTable('location_contexts')) {
            return 0;
        }

        $spaces = $this->personalSpaceIds($userId);

        return DB::table('location_contexts')
            ->where(function ($query) use ($userId, $spaces) {
                $query->where(function ($personal) use ($userId) {
                    $personal
                        ->where('subject_type', 'user')
                        ->where('subject_id', $userId);
                });

                if ($spaces !== []) {
                    $query->orWhereIn('financial_space_id', $spaces);
                }
            })
            ->delete();
    }

    private function personalSpaceIds(int $userId): array
    {
        if (! Schema::hasTable('financial_spaces') || ! Schema::hasTable('financial_space_memberships')) {
            return [];
        }

        return DB::table('financial_spaces as spaces')
            ->join(
                'financial_space_memberships as memberships',
                'memberships.financial_space_id',
                '=',
                'spaces.id',
            )
            ->where('memberships.user_id', $userId)
            ->where('spaces.type', 'personal')
            ->pluck('spaces.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
