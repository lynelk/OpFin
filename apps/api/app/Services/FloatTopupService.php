<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FloatTopup;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Disbursement float top-ups follow maker-checker: one staff member records a
 * pending top-up with its evidence and a different staff member approves it.
 * Only approval changes the Disbursement account balance.
 */
class FloatTopupService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function record(User $recorder, string $amount, ?string $imagePath = null, ?Request $request = null): FloatTopup
    {
        $topup = FloatTopup::create([
            'amount' => $amount,
            'image' => $imagePath,
            'status' => FloatTopup::STATUS_PENDING,
            'recorded_by_user_id' => $recorder->id,
        ]);

        $this->auditLogger->record('float_topup.recorded', $recorder, $topup, ['amount' => $amount], $request);

        return $topup;
    }

    public function approve(FloatTopup $topup, User $approver, ?Request $request = null): FloatTopup
    {
        return DB::transaction(function () use ($topup, $approver, $request): FloatTopup {
            $locked = FloatTopup::query()->whereKey($topup->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== FloatTopup::STATUS_PENDING) {
                throw new DomainException('This float top-up has already been decided.');
            }
            if ($locked->recorded_by_user_id === null || (int) $locked->recorded_by_user_id === (int) $approver->id) {
                throw new DomainException('A different staff member must approve this float top-up.');
            }

            $account = Account::query()->where('name', 'Disbursement')->lockForUpdate()->first();
            if (! $account) {
                throw new DomainException('The Disbursement account is not configured.');
            }

            Account::query()->whereKey($account->getKey())->increment('balance', $locked->amount);
            $locked->forceFill([
                'status' => FloatTopup::STATUS_APPROVED,
                'approved_by_user_id' => $approver->id,
                'approved_at' => now(),
                'account_id' => $account->getKey(),
            ])->save();

            $this->auditLogger->record('float_topup.approved', $approver, $locked, [
                'amount' => (string) $locked->amount,
                'account_id' => $account->getKey(),
                'recorded_by_user_id' => $locked->recorded_by_user_id,
            ], $request);

            return $locked;
        });
    }
}
