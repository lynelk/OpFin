<?php

namespace App\Services;

use App\Models\FinancialSpace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinancialSpacePayoutMandateService
{
    public function submit(FinancialSpace $space, User $maker, array $data): object
    {
        $this->admin($maker);
        $env = strtoupper((string) config('services.cito.environment', 'SANDBOX'));
        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $expires = Carbon::parse($data['expires_at'] ?? 'yesterday');
        $document = strtolower(trim((string) ($data['document_sha256'] ?? '')));
        foreach (['custody_agreement_reference', 'segregated_settlement_account_reference'] as $field) {
            if (! is_string($data[$field] ?? null) || ! preg_match('/^[A-Za-z0-9:._-]{6,128}$/', $data[$field])) {
                throw new InvalidArgumentException('Valid custody agreement and settlement references are required.');
            }
        }
        if (! preg_match('/^[a-f0-9]{64}$/', $document)
            || $currency !== strtoupper((string) $space->currency)
            || ! $expires->isFuture()) {
            throw new InvalidArgumentException('Mandate currency, evidence hash and expiry must be valid.');
        }
        $id = DB::table('financial_space_payout_mandates')->insertGetId([
            'financial_space_id' => $space->id, 'environment' => $env, 'currency' => $currency,
            'custody_agreement_reference' => $data['custody_agreement_reference'],
            'segregated_settlement_account_reference' => $data['segregated_settlement_account_reference'],
            'document_sha256' => $document, 'maker_user_id' => $maker->id,
            'status' => 'pending', 'expires_at' => $expires,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(AuditLogger::class)->record('financial_space.payout_mandate_requested', $maker, $space, [
            'mandate_id' => $id, 'environment' => $env, 'document_sha256' => $document,
        ]);

        return DB::table('financial_space_payout_mandates')->find($id);
    }

    public function approve(FinancialSpace $space, User $checker, int $mandateId): object
    {
        $this->admin($checker);

        return DB::transaction(function () use ($space, $checker, $mandateId) {
            $row = DB::table('financial_space_payout_mandates')
                ->where('financial_space_id', $space->id)->where('id', $mandateId)->lockForUpdate()->first();
            if (! $row || $row->status !== 'pending') {
                throw new InvalidArgumentException('Only a pending mandate in this Space can be approved.');
            }
            if ((int) $row->maker_user_id === (int) $checker->id
                || $row->environment !== strtoupper((string) config('services.cito.environment', 'SANDBOX'))
                || now()->gte($row->expires_at)) {
                throw new InvalidArgumentException('Mandate requires a separate checker, correct environment and valid expiry.');
            }
            DB::table('financial_space_payout_mandates')->where('id', $row->id)->update([
                'checker_user_id' => $checker->id,
                'approved_at' => now(), 'status' => 'approved', 'updated_at' => now(),
            ]);
            app(AuditLogger::class)->record('financial_space.payout_mandate_approved', $checker, $space, [
                'mandate_id' => $row->id, 'maker_user_id' => $row->maker_user_id,
                'environment' => $row->environment, 'document_sha256' => $row->document_sha256,
            ]);

            return DB::table('financial_space_payout_mandates')->find($row->id);
        });
    }

    public function assertAccepted(FinancialSpace $space, string $currency): void
    {
        if (! (bool) config('opfin.integrations.financial_space_payouts_accepted', false)) {
            throw new InvalidArgumentException('Financial Space payouts require independently accepted custody and settlement mandate controls.');
        }
        $valid = DB::table('financial_space_payout_mandates')
            ->where('financial_space_id', $space->id)
            ->where('environment', strtoupper((string) config('services.cito.environment', 'SANDBOX')))
            ->where('currency', strtoupper($currency))
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->where('expires_at', '>', now())
            ->exists();
        if (! $valid) {
            throw new InvalidArgumentException('No approved, unexpired custody and settlement mandate exists for this Financial Space.');
        }
    }

    private function admin(User $actor): void
    {
        if (! $actor->hasRole(User::ROLE_PLATFORM_ADMIN) || $actor->trashed()) {
            throw new AuthorizationException('Only platform administrators can review payout mandates.');
        }
    }
}
