<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanGuarantor;
use App\Models\Otp;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GuarantorService
{
    public function attachVerified(
        LoanApplication $application,
        int $borrowerUserId,
        string $phone,
        string $verificationToken,
        bool $consentConfirmed,
        ?string $name = null,
    ): LoanGuarantor {
        if ((int) $application->user_id !== $borrowerUserId) {
            throw new InvalidArgumentException('The application does not belong to this borrower.');
        }

        if (! $consentConfirmed) {
            throw new InvalidArgumentException('Electronic guarantor confirmation is required.');
        }

        $phone = trim($phone);
        if ($phone === '' || $phone === (string) $application->user?->phone) {
            throw new InvalidArgumentException('A guarantor must use a contact number different from the borrower.');
        }

        $existingCount = LoanGuarantor::query()->where('loan_application_id', $application->id)->count();
        $existing = LoanGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->where('phone', $phone)
            ->first();

        if (! $existing && $existingCount >= 2) {
            throw new InvalidArgumentException('UMRA guarantor-contact control allows a maximum of two guarantor contacts for a loan.');
        }

        $otp = Otp::query()->where('phone', $phone)->first();
        $valid = $otp
            && $otp->verified_at
            && $otp->verification_token_hash
            && $otp->verified_at->gte(now()->subMinutes(10))
            && hash_equals(
                $otp->verification_token_hash,
                hash('sha256', $verificationToken),
            );

        if (! $valid) {
            throw new InvalidArgumentException('The guarantor phone must be electronically verified before it can be attached.');
        }

        return DB::transaction(function () use ($application, $borrowerUserId, $phone, $name, $otp, $existingCount, $existing) {
            $guarantor = $existing ?: new LoanGuarantor;
            $guarantor->fill([
                'loan_application_id' => $application->id,
                'borrower_user_id' => $borrowerUserId,
                'position' => $existing?->position ?: ($existingCount + 1),
                'phone' => $phone,
                'name' => $name,
                'status' => LoanGuarantor::STATUS_VERIFIED,
                'verification_method' => 'otp',
                'verification_reference' => hash('sha256', $phone.'|'.$otp->verified_at->toIso8601String()),
                'consent_evidence' => [
                    'method' => 'electronic_otp',
                    'confirmed' => true,
                    'confirmed_at' => now()->toIso8601String(),
                    'notice' => 'Guarantor contact was deliberately supplied and electronically verified; no contact-list access was used.',
                ],
                'verified_at' => now(),
                'consented_at' => now(),
            ]);
            $guarantor->save();
            $otp->delete();

            return $guarantor->fresh();
        });
    }

    public function assertRequirementSatisfied(LoanApplication $application): void
    {
        $application->loadMissing('loanProductTerm');
        $required = min(2, max(0, (int) ($application->loanProductTerm?->guarantors_required ?? 0)));

        if ($required === 0) {
            return;
        }

        $verified = LoanGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->where('status', LoanGuarantor::STATUS_VERIFIED)
            ->whereNotNull('verified_at')
            ->whereNotNull('consented_at')
            ->count();

        if ($verified < $required) {
            throw new InvalidArgumentException("This product requires {$required} electronically verified guarantor contact(s) before credit decisioning.");
        }
    }
}
