<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CheckDocumentStorage extends Command
{
    protected $signature = 'opfin:storage-check';

    protected $description = 'Write, read back and delete a probe file on the KYC and statement disks, and warn when they are not durable';

    public function handle(): int
    {
        $disks = [
            'KYC documents' => (string) config('services.identity_verification.disk'),
            'Statement uploads' => (string) config('financial_intelligence.statement_disk'),
        ];
        $failed = false;
        foreach ($disks as $label => $disk) {
            $driver = (string) config("filesystems.disks.{$disk}.driver");
            if ($driver === 'local' && app()->environment('production')) {
                $this->warn("{$label}: disk '{$disk}' is the container's local disk. Files are lost on redeploy and are not shared with the worker or scheduler.");
            }
            $path = 'opfin-storage-check/'.Str::uuid().'.txt';
            $probe = 'opfin storage check '.now()->toIso8601String();
            try {
                $storage = Storage::disk($disk);
                $ok = $storage->put($path, $probe) && $storage->get($path) === $probe;
                $storage->delete($path);
            } catch (Throwable $exception) {
                // Report the failure class only: driver messages can include endpoints or key identifiers.
                $ok = false;
                $this->error("{$label}: disk '{$disk}' failed (".class_basename($exception).').');
            }
            if ($ok) {
                $this->info("{$label}: disk '{$disk}' ({$driver}) write, read and delete OK.");
            } else {
                $failed = true;
                $this->error("{$label}: disk '{$disk}' did not pass the write, read and delete check.");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
