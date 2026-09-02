<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\Loan;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function deleteOrRequest(User $user, string $password, Request $request): array
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => ['Your current password is incorrect.']]);
        }

        return DB::transaction(function () use ($user, $request) {
            $obligations = $this->activeObligations($user);
            if ($obligations !== []) {
                $case = SupportCase::query()
                    ->where('customer_id', $user->id)
                    ->where('category', 'account_deletion')
                    ->whereIn('status', [SupportCase::STATUS_OPEN, SupportCase::STATUS_IN_PROGRESS])
                    ->latest('id')
                    ->first();

                if (! $case) {
                    $case = SupportCase::create([
                        'customer_id' => $user->id,
                        'created_by' => $user->id,
                        'case_number' => 'CASE-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                        'category' => 'account_deletion',
                        'status' => SupportCase::STATUS_OPEN,
                        'priority' => 'high',
                        'subject' => 'Account deletion request',
                        'description' => 'Customer requested account deletion while regulated or financial obligations remain active. Close or transfer those obligations before completing deletion.',
                    ]);
                }

                $this->auditLogger->record('account.deletion.requested', $user, $case, [
                    'active_obligations' => $obligations,
                    'case_number' => $case->case_number,
                ], $request);

                return [
                    'deletion_status' => 'pending_obligations',
                    'case_number' => $case->case_number,
                    'active_obligations' => $obligations,
                    'message' => 'Your deletion request is recorded. The account remains available only so active regulated or financial obligations can be completed safely.',
                ];
            }

            ConsentRecord::query()
                ->where('user_id', $user->id)
                ->where('status', ConsentRecord::STATUS_GRANTED)
                ->update([
                    'status' => ConsentRecord::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->purgeOptionalCustomerContext($user->id);

            $retained = [
                'financial transaction and settlement evidence where legally required',
                'loan, repayment, accounting and reconciliation records where legally required',
                'KYC/AML, credit-reporting and regulatory evidence where legally required',
                'security, consent and audit evidence required to demonstrate lawful processing and account closure',
            ];

            $this->auditLogger->record('account.deletion.completed', $user, null, [
                'retained_record_categories' => $retained,
            ], $request);

            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'Deleted User',
                'phone' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(12)),
                'email' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(8)).'@deleted.invalid',
                'national_id' => null,
                'date_of_birth' => null,
                'nin_status' => null,
                'api_status' => null,
                'validated_at' => null,
                'phone_verified_at' => null,
                'email_verified_at' => null,
            ])->save();
            $user->delete();

            return [
                'deletion_status' => 'completed',
                'retained_record_categories' => $retained,
                'message' => 'Your OpFin account has been deleted. Records that must be retained for legal, regulatory, accounting or fraud-prevention purposes are isolated from active use and kept only for the required retention period.',
            ];
        });
    }

    private function activeObligations(User $user): array
    {
        $obligations = [];

        if (Loan::withoutGlobalScopes()->where('user_id', $user->id)->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected'])->exists()) {
            $obligations[] = 'active_credit';
        }

        if ($this->existsForUser('participatory_finance_listings', 'borrower_user_id', $user->id, ['awaiting_compliance_review', 'approved', 'funding', 'funded'])) {
            $obligations[] = 'peer_borrowing';
        }

        if ($this->existsForUser('participatory_finance_commitments', 'investor_user_id', $user->id, ['awaiting_step_up', 'provider_processing', 'settled'])) {
            $obligations[] = 'peer_lending';
        }

        if (Schema::hasTable('savings_goals') && Schema::hasColumn('savings_goals', 'user_id')) {
            $query = DB::table('savings_goals')->where('user_id', $user->id);
            if (Schema::hasColumn('savings_goals', 'confirmed_balance_minor')) {
                $query->where('confirmed_balance_minor', '>', 0);
            } elseif (Schema::hasColumn('savings_goals', 'status')) {
                $query->whereIn('status', ['active', 'withdrawal_pending']);
            }
            if ($query->exists()) {
                $obligations[] = 'savings_position';
            }
        }

        if (Schema::hasTable('protection_policies') && Schema::hasColumn('protection_policies', 'user_id')) {
            $query = DB::table('protection_policies')->where('user_id', $user->id);
            if (Schema::hasColumn('protection_policies', 'status')) {
                $query->whereIn('status', ['premium_due', 'active', 'claim_pending']);
            }
            if ($query->exists()) {
                $obligations[] = 'protection_policy';
            }
        }

        return array_values(array_unique($obligations));
    }

    private function existsForUser(string $table, string $userColumn, int $userId, array $activeStatuses): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $userColumn)) {
            return false;
        }

        $query = DB::table($table)->where($userColumn, $userId);
        if (Schema::hasColumn($table, 'status')) {
            $query->whereIn('status', $activeStatuses);
        }

        return $query->exists();
    }

    private function purgeOptionalCustomerContext(int $userId): void
    {
        foreach ([
            'financial_accounts',
            'financial_budgets',
            'financial_entries',
            'financial_calendar_events',
            'linked_financial_accounts',
            'household_finance_profiles',
            'microbusiness_profiles',
            'community_finance_memberships',
            'offline_sync_batches',
        ] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                DB::table($table)->where('user_id', $userId)->delete();
            }
        }
    }
}
