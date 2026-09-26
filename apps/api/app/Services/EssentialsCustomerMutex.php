<?php

namespace App\Services;

use App\Exceptions\EssentialsCustomerBusy;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Serialise financial changes without holding a database transaction across provider calls. */
class EssentialsCustomerMutex
{
    private array $held = [];

    public function run(int $userId, callable $operation): mixed
    {
        return $this->exclusive($userId, $operation, false);
    }

    /** Internal servicing only; this does not reopen customer login or authorise new credit. */
    public function runRetainedServicing(int $userId, callable $operation): mixed
    {
        return $this->exclusive($userId, $operation, true);
    }

    private function exclusive(int $userId, callable $operation, bool $includeClosed): mixed
    {
        if ($userId <= 0) {
            throw new RuntimeException('An existing customer is required.');
        }
        if (isset($this->held[$userId])) {
            return $operation($this->customer($userId, $includeClosed));
        }
        $connection = DB::connection();
        $name = 'opfin:essentials:customer:'.$userId;
        $cacheLock = null;
        $postgres = $connection->getDriverName() === 'pgsql';
        if ($postgres) {
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
            return $operation($this->customer($userId, $includeClosed));
        } finally {
            unset($this->held[$userId]);
            if ($postgres) {
                try {
                    $connection->selectOne('SELECT pg_advisory_unlock(hashtextextended(?, 0)) AS released', [$name], false);
                } catch (Throwable) {
                    $connection->disconnect();
                }
            } else {
                $cacheLock?->release();
            }
        }
    }

    private function customer(int $userId, bool $includeClosed): User
    {
        $query = User::withoutGlobalScopes();
        if (! $includeClosed) { $query->whereNull('deleted_at'); }
        return $query->findOrFail($userId);
    }
}
