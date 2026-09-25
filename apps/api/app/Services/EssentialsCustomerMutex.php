<?php

namespace App\Services;

use App\Exceptions\EssentialsCustomerBusy;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Serialise Essentials mutations and account closure without moving the
 * existing database commit boundary across an external provider request.
 * PostgreSQL must use a direct or session-affine writer connection.
 */
class EssentialsCustomerMutex
{
    private array $held = [];

    public function run(int $userId, callable $operation): mixed
    {
        if ($userId <= 0) {
            throw new RuntimeException('An existing customer is required.');
        }
        if (isset($this->held[$userId])) {
            return $operation($this->activeCustomer($userId));
        }
        $connection = DB::connection();
        $name = 'opfin:essentials:customer:'.$userId;
        $cacheLock = null;
        $postgres = $connection->getDriverName() === 'pgsql';
        if ($postgres) {
            // Session-level, not transaction-level: the native service can
            // commit its reservation before submitting a provider request.
            // Use the writer PDO for both lock and unlock, never a read replica.
            $result = $connection->selectOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired', [$name], false);
            if (! in_array($result->acquired, [true, 1, '1', 't', 'true'], true)) {
                throw new EssentialsCustomerBusy('Another financial operation is in progress for this customer. Refresh its status before retrying.');
            }
        } else {
            if (! in_array(app()->environment(), ['local', 'testing'], true)) {
                throw new RuntimeException('This financial concurrency control requires the approved PostgreSQL environment.');
            }
            $cacheLock = Cache::lock($name, 300);
            if (! $cacheLock->get()) {
                throw new EssentialsCustomerBusy('Another financial operation is in progress for this customer. Refresh its status before retrying.');
            }
        }
        $this->held[$userId] = true;
        try {
            return $operation($this->activeCustomer($userId));
        } finally {
            unset($this->held[$userId]);
            if ($postgres) {
                try {
                    $connection->selectOne('SELECT pg_advisory_unlock(hashtextextended(?, 0)) AS released', [$name], false);
                } catch (Throwable) {
                    // Closing the session releases its advisory locks. Never
                    // retry the financial callback because unlocking failed.
                    $connection->disconnect();
                }
            } else {
                $cacheLock?->release();
            }
        }
    }

    private function activeCustomer(int $userId): User
    {
        return User::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($userId);
    }
}
