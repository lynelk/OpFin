<?php

namespace App\Services;

use App\Models\FinancialIntent;
use App\Models\FinancialProduct;
use App\Models\FinancingApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FinancingService
{
    public const PREFERENCES = ['ALL_SUITABLE', 'SHARIA_ONLY', 'CONVENTIONAL_ONLY'];

    public function createIntent(User $user, array $data): FinancialIntent
    {
        $preference = strtoupper((string) ($data['principles_preference'] ?? 'ALL_SUITABLE'));
        if (!in_array($preference, self::PREFERENCES, true)) {
            throw new InvalidArgumentException('Unsupported financial principles preference.');
        }
        $spaceId = (int) $data['financial_space_id'];
        $this->assertSpaceAuthority($user, $spaceId);

        return FinancialIntent::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'need_type' => $data['need_type'],
            'principles_preference' => $preference,
            'amount_minor' => $data['amount_minor'] ?? null,
            'currency' => strtoupper((string) ($data['currency'] ?? 'UGX')),
            'purpose' => $data['purpose'] ?? null,
            'status' => 'open',
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    public function matchingProducts(FinancialIntent $intent)
    {
        $query = FinancialProduct::query()
            ->where('status', 'live')
            ->where('jurisdiction', 'UG')
            ->where('currency', $intent->currency)
            ->where(function ($q) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', now());
            })
            ->whereHas('legalPassport', function ($q) {
                $q->where('status', 'approved')
                    ->where(function ($q) {
                        $q->whereNull('effective_from')->orWhere('effective_from', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('effective_to')->orWhere('effective_to', '>', now());
                    });
            });

        if ($intent->principles_preference === 'SHARIA_ONLY') {
            $query->where('rail', 'ISLAMIC')->whereHas('shariaApproval', function ($q) {
                $q->where('status', 'approved')
                    ->where(function ($q) {
                        $q->whereNull('effective_from')->orWhere('effective_from', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            });
        } elseif ($intent->principles_preference === 'CONVENTIONAL_ONLY') {
            $query->where('rail', 'CONVENTIONAL');
        }

        return $query->orderBy('family')->orderBy('name')->get();
    }

    public function apply(User $user, FinancialIntent $intent, FinancialProduct $product): FinancingApplication
    {
        if ((int) $intent->user_id !== (int) $user->id || $intent->status !== 'open') {
            throw new InvalidArgumentException('This financial intent is not available.');
        }
        $this->assertSpaceAuthority($user, (int) $intent->financial_space_id);
        $matches = $this->matchingProducts($intent);
        if (!$matches->contains('id', $product->id)) {
            throw new InvalidArgumentException('This product is not suitable for the selected financial principles or is not activated.');
        }

        return FinancingApplication::create([
            'reference' => (string) Str::uuid(),
            'financial_intent_id' => $intent->id,
            'financial_product_id' => $product->id,
            'user_id' => $user->id,
            'financial_space_id' => $intent->financial_space_id,
            'status' => 'submitted',
            'suitability_snapshot' => [
                'principles_preference' => $intent->principles_preference,
                'product_rail' => $product->rail,
                'product_version' => $product->version,
            ],
        ]);
    }

    private function assertSpaceAuthority(User $user, int $spaceId): void
    {
        $allowed = DB::table('financial_space_memberships')
            ->where('financial_space_id', $spaceId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
        if (!$allowed) {
            throw new InvalidArgumentException('You do not have active authority in that Financial Space.');
        }
    }
}
