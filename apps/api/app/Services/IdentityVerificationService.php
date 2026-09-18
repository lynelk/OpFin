<?php

namespace App\Services;

use App\Models\CustomerPhoneNumber;
use App\Models\KycCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IdentityVerificationService
{
    public function verify(KycCase $case): KycCase
    {
        $url = trim((string) config('services.identity_verification.url'));
        $token = trim((string) config('services.identity_verification.token'));

        if ($url === '') {
            $case->update([
                'liveness_status' => 'pending_review',
                'face_match_status' => 'pending_review',
                'nin_phone_link_status' => 'pending_review',
                'risk_flags' => array_values(array_unique([
                    ...($case->risk_flags ?? []),
                    'IDENTITY_PROVIDER_NOT_CONFIGURED',
                ])),
            ]);

            return $case->fresh();
        }

        try {
            $front = Storage::disk('local')->get($case->national_id_front_path);
            $back = Storage::disk('local')->get($case->national_id_back_path);
            $selfie = Storage::disk('local')->get($case->selfie_with_id_path);
            $phones = CustomerPhoneNumber::query()
                ->where('user_id', $case->user_id)
                ->whereNotNull('verified_at')
                ->pluck('phone')
                ->values()
                ->all();

            $request = Http::acceptJson()->timeout(15)
                ->attach('national_id_front', $front, 'national-id-front.jpg')
                ->attach('national_id_back', $back, 'national-id-back.jpg')
                ->attach('selfie_with_id', $selfie, 'selfie-with-id.jpg');

            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post($url, [
                'nin' => $case->national_id,
                'phones' => json_encode($phones, JSON_THROW_ON_ERROR),
            ]);
            $response->throw();

            $ninValid = (bool) $response->json('nin_valid', false);
            $livenessValid = (bool) $response->json('liveness_valid', false);
            $faceMatchValid = (bool) $response->json('face_match_valid', false);
            $phoneLinkValid = (bool) $response->json('nin_phone_link_valid', false);
            $verified = $ninValid && $livenessValid && $faceMatchValid && $phoneLinkValid;

            $case->update([
                'provider' => 'configured_identity_provider',
                'provider_reference' => $response->json('reference'),
                'liveness_status' => $livenessValid ? 'valid' : 'failed',
                'face_match_status' => $faceMatchValid ? 'valid' : 'failed',
                'nin_phone_link_status' => $phoneLinkValid ? 'valid' : 'failed',
                'status' => $verified ? KycCase::STATUS_VERIFIED : KycCase::STATUS_PENDING_REVIEW,
                'risk_flags' => array_values(array_filter([
                    $ninValid ? null : 'NIN_VALIDATION_FAILED',
                    $livenessValid ? null : 'LIVENESS_CHECK_FAILED',
                    $faceMatchValid ? null : 'FACE_MATCH_FAILED',
                    $phoneLinkValid ? null : 'NIN_PHONE_LINK_FAILED',
                ])),
                'reviewed_at' => $verified ? now() : null,
                'expires_at' => $verified ? now()->addYear() : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $case->update([
                'liveness_status' => 'provider_error',
                'face_match_status' => 'provider_error',
                'nin_phone_link_status' => 'provider_error',
                'risk_flags' => array_values(array_unique([
                    ...($case->risk_flags ?? []),
                    'IDENTITY_PROVIDER_ERROR',
                ])),
            ]);
        }

        return $case->fresh();
    }
}
