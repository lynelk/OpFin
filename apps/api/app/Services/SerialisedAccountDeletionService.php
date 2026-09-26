<?php

namespace App\Services;

use App\Models\EssentialsCollectionInstruction;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SerialisedAccountDeletionService extends AccountDeletionService
{
    public function deleteOrRequest(User $user, string $credential, Request $request): array
    {
        return app(EssentialsCustomerMutex::class)->run($user->id, function (User $current) use ($credential, $request): array {
            if (! Hash::check($credential, $current->password)) {
                throw ValidationException::withMessages(['pin' => ['Your current PIN or legacy password is incorrect.']]);
            }
            $unresolved = Schema::hasTable('essentials_collection_instructions')
                && EssentialsCollectionInstruction::query()->join('essentials_repayments as repayment', 'repayment.id', '=', 'essentials_collection_instructions.repayment_id')
                    ->where('essentials_collection_instructions.user_id', $current->id)
                    ->where(function ($query): void {
                        $query->whereNotIn('essentials_collection_instructions.status', ['applied', 'failed', 'reversed'])
                            ->orWhere(function ($reversed): void {
                                $reversed->where('essentials_collection_instructions.status', 'reversed')->where('repayment.status', '<>', 'reversed');
                            });
                    })->exists();
            if (! $unresolved) {
                return parent::deleteOrRequest($current, $credential, $request);
            }
            return DB::transaction(function () use ($current, $request): array {
                $case = SupportCase::where('customer_id', $current->id)->where('category', 'account_deletion')
                    ->whereIn('status', [SupportCase::STATUS_OPEN, SupportCase::STATUS_IN_PROGRESS])->latest('id')->first();
                if (! $case) {
                    $case = SupportCase::create(['customer_id' => $current->id, 'created_by' => $current->id,
                        'case_number' => 'CASE-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                        'category' => 'account_deletion', 'status' => SupportCase::STATUS_OPEN, 'priority' => 'high',
                        'subject' => 'Account deletion request',
                        'description' => 'Reconcile unresolved collection or reversal evidence before deleting customer servicing access.']);
                }
                app(AuditLogger::class)->record('account.deletion.requested', $current, $case,
                    ['active_obligations' => ['essentials_collection_reconciliation'], 'case_number' => $case->case_number], $request);
                return ['deletion_status' => 'pending_obligations', 'case_number' => $case->case_number,
                    'active_obligations' => ['essentials_collection_reconciliation'],
                    'message' => 'Your deletion request is recorded. Resolve the pending collection evidence first; your servicing access remains available.'];
            });
        });
    }
}
