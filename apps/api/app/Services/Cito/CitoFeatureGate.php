<?php

namespace App\Services\Cito;

use RuntimeException;

/**
 * Fail-closed consumer feature gate. Source availability is not an entitlement.
 * P2 channels must not silently assume a published Cito external API exists.
 */
class CitoFeatureGate
{
    public function enabled(string $capability): bool
    {
        $capability = strtolower(trim($capability));
        $supported = ['sms', 'otp', 'otp_whatsapp', 'identity', 'credit', 'payments', 'billing'];
        if (! in_array($capability, $supported, true)) {
            return false;
        }
        if ($capability === 'otp_whatsapp' && ! $this->enabled('otp')) {
            return false;
        }
        if (! (bool) config('services.cito.feature_flags.'.$capability, false)) {
            return false;
        }
        $environment = strtoupper(trim((string) config('services.cito.environment', 'SANDBOX')));
        if (! in_array($environment, ['SANDBOX', 'PRODUCTION'], true)) {
            return false;
        }
        // Production features require independently recorded entitlement and release acceptance.
        if ($environment === 'PRODUCTION' && ! (bool) config('services.cito.accepted_capabilities.'.$capability, false)) {
            return false;
        }
        return true;
    }

    public function requireEnabled(string $capability): void
    {
        if (! $this->enabled($capability)) {
            throw new RuntimeException('Cito capability is not enabled or accepted for this environment.');
        }
    }
}
