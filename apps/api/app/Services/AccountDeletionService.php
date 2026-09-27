<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AccountDeletionObligationService $obligations,
        private readonly AccountDataDeletionService $dataDeletion,
    ) {}

    public function readiness(User $user): array
    {
        $active=$this->obligations->forUser($user);

        return [
            'can_delete_account'=>$active===[],
            'active_obligation_count'=>count($active),
            'active_obligations'=>$active,
            'data_deletion'=>[
                'available_categories'=>$this->dataDeletion->options(),
                'retained_record_categories'=>AccountDataDeletionService::RETAINED,
            ],
            'message'=>$active===[]
                ? 'No active financial obligation currently prevents account deletion.'
                : 'Account deletion is blocked until the listed obligations are settled, closed or otherwise resolved with the relevant provider.',
        ];
    }

    public function deleteOrRequest(User $user,string $credential,Request $request): array
    {
        $this->assertCredential($user,$credential);

        return DB::transaction(function()use($user,$credential,$request){
            if (Schema::hasTable('credit_profiles')) {
                DB::table('credit_profiles')->where('user_id',$user->id)->lockForUpdate()->first();
            }

            $user=User::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($user->id)
                ->lockForUpdate()->firstOrFail();
            $this->assertCredential($user,$credential);

            $active=$this->obligations->forUser($user);
            if ($active!==[]) {
                $this->auditLogger->record('account.deletion.rejected_obligations',$user,null,[
                    'active_obligation_count'=>count($active),
                    'active_obligations'=>$active,
                ],$request);

                return [
                    'deletion_status'=>'blocked_obligations',
                    'can_retry'=>true,
                    'active_obligation_count'=>count($active),
                    'active_obligations'=>$active,
                    'message'=>'Your account cannot be deleted while financial obligations remain. Resolve each listed obligation with the relevant provider, then submit the deletion request again.',
                ];
            }

            ConsentRecord::query()->where('user_id',$user->id)
                ->where('status',ConsentRecord::STATUS_GRANTED)
                ->update(['status'=>ConsentRecord::STATUS_REVOKED,'revoked_at'=>now(),'updated_at'=>now()]);

            $this->dataDeletion->purgeForClosedAccount($user->id);

            $this->auditLogger->record('account.deletion.completed',$user,null,[
                'retained_record_categories'=>AccountDataDeletionService::RETAINED,
            ],$request);

            $user->tokens()->delete();
            $user->forceFill([
                'name'=>'Deleted User',
                'first_name'=>null,
                'other_name'=>null,
                'last_name'=>null,
                'preferred_language'=>'en',
                'accessibility_preferences'=>null,
                'phone'=>'deleted-'.$user->id.'-'.Str::lower(Str::random(12)),
                'email'=>'deleted-'.$user->id.'-'.Str::lower(Str::random(8)).'@deleted.invalid',
                'national_id'=>null,
                'date_of_birth'=>null,
                'nin_status'=>null,
                'api_status'=>null,
                'validated_at'=>null,
                'phone_verified_at'=>null,
                'email_verified_at'=>null,
            ])->save();
            $user->delete();

            return [
                'deletion_status'=>'completed',
                'retained_record_categories'=>AccountDataDeletionService::RETAINED,
                'message'=>'Your OpFin account has been deleted. Records that must be retained for legal, regulatory, accounting or fraud-prevention purposes are isolated from active use and kept only for the required retention period.',
            ];
        });
    }

    public function deleteSelectedData(
        User $user,
        string $credential,
        array $categories,
        Request $request,
    ): array {
        $this->assertCredential($user,$credential);

        return DB::transaction(function()use($user,$credential,$categories,$request){
            $locked=User::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($user->id)
                ->lockForUpdate()->firstOrFail();
            $this->assertCredential($locked,$credential);

            $deleted=$this->dataDeletion->delete($locked,$categories);

            $this->auditLogger->record('account.data_deletion.completed',$locked,null,[
                'requested_categories'=>array_values(array_unique($categories)),
                'deleted_record_counts'=>$deleted,
                'retained_record_categories'=>AccountDataDeletionService::RETAINED,
            ],$request);

            return [
                'deletion_status'=>'data_deleted',
                'deleted_categories'=>array_keys($deleted),
                'deleted_record_counts'=>$deleted,
                'retained_record_categories'=>AccountDataDeletionService::RETAINED,
                'message'=>'The selected optional data has been deleted. Regulated financial, identity, accounting, settlement, security and audit records remain subject to their applicable retention requirements.',
            ];
        });
    }

    private function assertCredential(User $user,string $credential): void
    {
        if (! Hash::check($credential,$user->password)) {
            throw ValidationException::withMessages([
                'pin'=>['Your current PIN or legacy password is incorrect.'],
            ]);
        }
    }
}
