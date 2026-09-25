<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PartnerEssentialsController;
use App\Http\Controllers\Api\ScopedPartnerEssentialsController;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\EssentialsCustomerMutex;
use App\Services\EssentialsOrchestrationService;
use App\Services\SerialisedAccountDeletionService;
use App\Services\SerialisedEssentialsOrchestrationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class EssentialsCustomerMutexTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_binds_all_exposed_implementations_to_the_controls(): void
    {
        $this->assertInstanceOf(SerialisedEssentialsOrchestrationService::class, app(EssentialsOrchestrationService::class));
        $this->assertInstanceOf(SerialisedAccountDeletionService::class, app(AccountDeletionService::class));
        $this->assertInstanceOf(ScopedPartnerEssentialsController::class, app(PartnerEssentialsController::class));
        $this->assertSame(app(EssentialsCustomerMutex::class), app(EssentialsCustomerMutex::class));
    }

    public function test_the_mutex_does_not_wrap_the_provider_operation_in_a_new_database_transaction(): void
    {
        $user = User::factory()->create();
        $depth = DB::transactionLevel();
        $result = app(EssentialsCustomerMutex::class)->run($user->id, function (User $current) use ($depth): int {
            $this->assertSame($depth, DB::transactionLevel());
            return app(EssentialsCustomerMutex::class)->run($current->id, function (User $same) use ($depth): int {
                $this->assertSame($depth, DB::transactionLevel());
                return $same->id;
            });
        });
        $this->assertSame($user->id, $result);
    }

    public function test_exceptions_release_the_mutex_without_replaying_the_operation(): void
    {
        $user = User::factory()->create();
        $mutex = app(EssentialsCustomerMutex::class);
        $calls = 0;
        try {
            $mutex->run($user->id, function () use (&$calls): never {
                $calls++;
                throw new LogicException('Synthetic failure after a provider boundary.');
            });
            $this->fail('The original exception must remain visible.');
        } catch (LogicException) {
            $this->assertSame(1, $calls);
        }
        $this->assertSame($user->id, $mutex->run($user->id, fn (User $current): int => $current->id));
    }

    public function test_a_competing_owner_cannot_enter_the_same_test_mutex(): void
    {
        $user = User::factory()->create();
        $lock = Cache::lock('opfin:essentials:customer:'.$user->id, 300);
        $this->assertTrue($lock->get());
        try {
            $this->expectException(RuntimeException::class);
            app(EssentialsCustomerMutex::class)->run($user->id, fn (): string => 'must-not-run');
        } finally {
            $lock->release();
        }
    }

    public function test_a_stale_user_object_cannot_resume_financial_work_after_deletion(): void
    {
        $user = User::factory()->create();
        $id = $user->id;
        $user->delete();
        $this->expectException(ModelNotFoundException::class);
        app(EssentialsCustomerMutex::class)->run($id, fn (): string => 'must-not-run');
    }
}
