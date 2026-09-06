<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

class WaitForDeploymentSchema extends Command
{
    protected $signature = 'deployment:wait-for-schema {--timeout=300 : Maximum wait in seconds}';

    protected $description = 'Wait without modifying the database until this release has no pending migrations.';

    public function handle(Migrator $migrator): int
    {
        $timeout = filter_var($this->option('timeout'), FILTER_VALIDATE_INT);
        if ($timeout === false || $timeout < 0 || $timeout > 900) {
            $this->error('Timeout must be an integer between 0 and 900 seconds.');

            return self::FAILURE;
        }

        $files = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));
        $deadline = microtime(true) + $timeout;
        do {
            try {
                $repository = $migrator->getRepository();
                if ($repository->repositoryExists()) {
                    $pending = array_diff(array_keys($files), $repository->getRan());
                    if ($pending === []) {
                        $this->info('Deployment schema ready; background processing may start.');

                        return self::SUCCESS;
                    }
                    $this->warn('Waiting for API migrations: '.count($pending).' pending.');
                } else {
                    $this->warn('Waiting for the API to initialize the migration repository.');
                }
            } catch (Throwable) {
                // Do not print database connection details or credentials.
                $this->warn('Schema readiness unavailable; background processing remains blocked.');
            }

            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                break;
            }
            usleep((int) (min(5, $remaining) * 1000000));
        } while (true);

        $this->error('Deployment schema is not ready. Refusing to start background processing.');

        return self::FAILURE;
    }
}
