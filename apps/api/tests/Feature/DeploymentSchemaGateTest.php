<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DeploymentSchemaGateTest extends TestCase
{
    private function repository(): DatabaseMigrationRepository
    {
        $repository = Mockery::mock(DatabaseMigrationRepository::class);
        $migrator = Mockery::mock(Migrator::class);
        $migrator->shouldReceive('paths')->once()->andReturn([]);
        $migrator->shouldReceive('getMigrationFiles')->once()->andReturn([
            'required_migration' => '/unused/required_migration.php',
        ]);
        $migrator->shouldReceive('getRepository')->once()->andReturn($repository);
        $this->app->instance('migrator', $migrator);
        $this->app->instance(Migrator::class, $migrator);

        return $repository;
    }

    public function test_background_processing_is_allowed_only_after_all_migrations(): void
    {
        $repository = $this->repository();
        $repository->shouldReceive('repositoryExists')->once()->andReturn(true);
        $repository->shouldReceive('getRan')->once()->andReturn(['required_migration']);

        $this->artisan('deployment:wait-for-schema', ['--timeout' => 0])->assertExitCode(0);
    }

    public function test_pending_migrations_fail_closed_without_running_migrations(): void
    {
        $repository = $this->repository();
        $repository->shouldReceive('repositoryExists')->once()->andReturn(true);
        $repository->shouldReceive('getRan')->once()->andReturn([]);

        $this->artisan('deployment:wait-for-schema', ['--timeout' => 0])->assertExitCode(1);
    }

    public function test_a_missing_repository_fails_closed(): void
    {
        $repository = $this->repository();
        $repository->shouldReceive('repositoryExists')->once()->andReturn(false);

        $this->artisan('deployment:wait-for-schema', ['--timeout' => 0])->assertExitCode(1);
    }

    public function test_connection_failures_do_not_start_background_processing(): void
    {
        $repository = $this->repository();
        $repository->shouldReceive('repositoryExists')->once()->andThrow(new RuntimeException('connection unavailable'));

        $this->artisan('deployment:wait-for-schema', ['--timeout' => 0])
            ->expectsOutput('Schema readiness unavailable; background processing remains blocked.')
            ->assertExitCode(1);
    }
}
