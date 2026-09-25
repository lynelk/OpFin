<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class SerialisedAccountDeletionService extends AccountDeletionService
{
    public function deleteOrRequest(User $user, string $credential, Request $request): array
    {
        return app(EssentialsCustomerMutex::class)->run($user->id,
            fn (User $current): array => parent::deleteOrRequest($current, $credential, $request));
    }
}
