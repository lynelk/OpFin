<?php

namespace App\Services;

use App\Models\CustomerWallet;
use App\Models\User;
use InvalidArgumentException;

class VerifiedWalletService
{
    /**
     * @return array{wallet:?CustomerWallet,phone:string}
     */
    public function forDisbursement(User $user, ?int $walletId = null): array
    {
        return $this->resolve($user, $walletId, 'is_default_disbursement', 'disbursement', true);
    }

    /**
     * @return array{wallet:?CustomerWallet,phone:string}
     */
    public function forRepayment(User $user, ?int $walletId = null): array
    {
        return $this->resolve($user, $walletId, 'is_default_repayment', 'repayment', false);
    }

    /**
     * @return array{wallet:?CustomerWallet,phone:string}
     */
    private function resolve(User $user, ?int $walletId, string $defaultColumn, string $purpose, bool $requireVerifiedWallet): array
    {
        if (! in_array($defaultColumn, ['is_default_disbursement', 'is_default_repayment'], true)) {
            throw new InvalidArgumentException('Unsupported verified-wallet purpose.');
        }

        $query = CustomerWallet::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('verified_at');

        $wallet = $walletId
            ? (clone $query)->whereKey($walletId)->first()
            : (clone $query)->where($defaultColumn, true)->first();

        if ($walletId && ! $wallet) {
            throw new InvalidArgumentException(
                "Choose a verified {$purpose} wallet that belongs to your OpFin profile."
            );
        }

        $wallet ??= (clone $query)->orderByDesc($defaultColumn)->orderBy('id')->first();

        if ($requireVerifiedWallet && ! $wallet) {
            throw new InvalidArgumentException(
                "A verified {$purpose} wallet is required before money can be sent from OpFin."
            );
        }

        $phone = trim((string) ($wallet?->msisdn ?: $user->phone));

        if ($phone === '') {
            throw new InvalidArgumentException(
                "A verified {$purpose} wallet or customer phone number is required."
            );
        }

        return ['wallet' => $wallet, 'phone' => $phone];
    }
}
