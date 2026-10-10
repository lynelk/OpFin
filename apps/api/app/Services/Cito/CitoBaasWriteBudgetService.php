<?php

namespace App\Services\Cito;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CitoBaasWriteBudgetService
{
    /**
     * Reserve a conservative external side-effect budget before sending. A
     * timeout DOES NOT free a reservation; manual reconciliation is required.
     * Caller must already be inside a DB transaction.
     */
    public function reserve(): void
    {
        $limit = (int) config('services.cito.baas_daily_write_limit', 0);
        if ($limit < 1) {
            throw new RuntimeException('Cito BaaS writes are blocked until a daily operations limit is approved.');
        }
        $environment = strtoupper((string) config('services.cito.environment', 'SANDBOX'));
        $date = now('UTC')->toDateString();

        DB::table('cito_baas_daily_write_limits')->insertOrIgnore([
            'environment' => $environment, 'business_date' => $date,
            'reserved_calls' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = DB::table('cito_baas_daily_write_limits')
            ->where('environment', $environment)->where('business_date', $date)
            ->lockForUpdate()->first();
        if (! $row || (int) $row->reserved_calls >= $limit) {
            throw new RuntimeException('Cito BaaS daily write budget is exhausted.');
        }
        DB::table('cito_baas_daily_write_limits')->where('id', $row->id)->update([
            'reserved_calls' => (int) $row->reserved_calls + 1, 'updated_at' => now(),
        ]);
    }

    public function today(): array
    {
        $environment = strtoupper((string) config('services.cito.environment', 'SANDBOX'));
        $row = DB::table('cito_baas_daily_write_limits')
            ->where('environment', $environment)->where('business_date', now('UTC')->toDateString())->first();

        return [
            'environment' => $environment,
            'limit' => (int) config('services.cito.baas_daily_write_limit', 0),
            'reserved_calls' => (int) ($row->reserved_calls ?? 0),
        ];
    }
}
