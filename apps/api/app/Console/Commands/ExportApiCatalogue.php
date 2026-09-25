<?php

namespace App\Console\Commands;

use App\Services\ApiDocumentation\ApiDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ExportApiCatalogue extends Command
{
    protected $signature = 'api:catalogue {--output= : Local export directory; defaults to storage/app/developer-catalogue} {--check : Validate without writing exports} {--require-complete : Fail if any registered operation lacks a reviewed contract} {--baseline= : Compare against an earlier catalogue-snapshot.json}';

    protected $description = 'Export current route discovery, reviewed OpenAPI, developer guides and explicit contract-coverage gaps.';

    public function handle(ApiDiscovery $discovery): int
    {
        try {
            $catalogue = $discovery->catalogue();
            $coverage = $catalogue->coverage(null, true);
            $changes = [];
            if ($this->option('baseline')) {
                $path = (string) $this->option('baseline');
                if (! is_file($path) || is_link($path) || filesize($path) > 10 * 1024 * 1024) {
                    throw new \InvalidArgumentException('Baseline must be a local, non-symlink catalogue snapshot under 10 MB.');
                }
                $baseline = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
                $changes = $catalogue->diff($baseline);
                if (($baseline['provenance']['runtime_fingerprint'] ?? null) !== $coverage['provenance']['runtime_fingerprint']) {
                    $this->warn('Runtime source changed. Review domain-service, validation and permission effects; route-shape checks alone do not establish compatibility.');
                }
            } else {
                $this->warn('No baseline supplied: compatibility/change-impact comparison was NOT run.');
            }
            $this->line(json_encode(['coverage' => $coverage, 'changes' => $changes], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $blocking = $coverage['definition_errors'] !== []
                || ($this->option('require-complete') && ! $coverage['complete'])
                || array_filter($changes, static fn (array $change): bool => $change['review_required']);
            if (! $this->option('check')) {
                $directory = $this->option('output') ?: storage_path('app/developer-catalogue');
                if (is_link($directory)) {
                    throw new \InvalidArgumentException('Export directory may not be a symlink.');
                }
                File::ensureDirectoryExists($directory);
                $guideData = [];
                foreach ($catalogue->guides() as $guide) {
                    $guideData[] = $catalogue->guide($guide['id']);
                }
                foreach ([
                    'catalogue-snapshot.json' => $catalogue->snapshot(),
                    'coverage.json' => $coverage,
                    'reviewed-openapi.json' => $catalogue->openApi('platform_admin'),
                    'guides.json' => $guideData,
                    'changes.json' => $changes,
                ] as $name => $value) {
                    $target = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;
                    if (is_link($target)) {
                        throw new \InvalidArgumentException('An export target may not be a symlink.');
                    }
                    File::replace($target, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                }
                $this->info('Exported source evidence to '.$directory.'. No API operation or external provider was invoked.');
            }
            if ($blocking) {
                $this->error('Requested contract checks failed. Read the coverage/change report; do not relabel registration-only operations as complete.');

                return self::FAILURE;
            }
            $this->info('Requested definition checks passed. This is not full API, provider or financial-release certification.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Developer-owned local paths/errors only. Do not dump environment or request contents.
            $this->error('Catalogue export failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
