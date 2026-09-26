<?php

namespace App\Services;

use App\Models\CreditProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Preserve the existing scoring decision, then cap it by complete financial exposure. */
class SerialisedCreditProfileService extends CustomerCreditProfileService
{
    public function refresh(User $user, bool $fetchExternal = true): CreditProfile
    {
        return app(EssentialsCustomerMutex::class)->run($user->id, function (User $current) use ($fetchExternal): CreditProfile {
            $profile = parent::refresh($current, $fetchExternal);
            DB::transaction(fn () => app(EssentialsExposureSynchroniser::class)->synchronise($current->id));
            return $profile->fresh();
        });
    }

    public function status(User $user): array
    {
        return app(EssentialsCustomerMutex::class)->run($user->id, function (User $current): array {
            $exposure = DB::transaction(fn () => app(EssentialsExposureSynchroniser::class)->synchronise($current->id));
            $result = parent::status($current);
            $result['essentials_reserved_minor'] = $exposure['essentials_reserved_minor'];
            $result['reserved_financing_is_not_due_debt'] = true;
            return $result;
        });
    }
}
