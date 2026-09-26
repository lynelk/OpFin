<?php

namespace App\Services\ApiDocumentation;

use App\Support\ApiDocumentation\ContractCatalogue;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionFunction;
use RuntimeException;

class ApiDiscovery
{
    private ?ContractCatalogue $built = null;

    public function catalogue(): ContractCatalogue
    {
        if ($this->built !== null) {
            return $this->built;
        }
        $contracts = require resource_path('developer-guides/contracts.php');
        $manifest = require resource_path('developer-guides/manifest.php');
        $guides = [];
        foreach ($manifest as $id => $entry) {
            if (! preg_match('/^[a-z][a-z0-9-]{0,60}$/D', $id)) {
                throw new RuntimeException('Invalid configured developer guide identifier.');
            }
            // File names are developer-owned constants, never request data.
            $file = resource_path('developer-guides/'.$entry['file']);
            $root = realpath(resource_path('developer-guides'));
            $resolved = realpath($file);
            if ($root === false || $resolved === false || is_link($file)
                || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) || filesize($resolved) > 262144) {
                throw new RuntimeException('Developer guide source is missing or outside its allow-list.');
            }
            $text = file_get_contents($resolved);
            $guides[$id] = ['id' => $id, 'title' => $entry['title'], 'audience' => $entry['audience'],
                'text' => $text, 'sha256' => hash('sha256', $text)];
        }
        $runtimeDigest = $this->runtimeDigest();
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $controllerDigest = null;
            $action = $route->getAction('uses');
            try {
                $file = $action instanceof \Closure
                    ? (new ReflectionFunction($action))->getFileName()
                    : (is_string($action) && str_contains($action, '@')
                        ? (new ReflectionClass(explode('@', $action, 2)[0]))->getFileName() : false);
                if (is_string($file) && is_file($file) && ! is_link($file)) {
                    $controllerDigest = hash_file('sha256', $file);
                }
            } catch (\ReflectionException) {
                // A missing handler remains an explicit validation error.
                $controllerDigest = 'unresolved-handler';
            }
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $routes[] = ['method' => $method, 'path' => '/'.$route->uri(),
                    'middleware' => $route->gatherMiddleware(),
                    'source_digest' => hash('sha256', json_encode([$route->uri(), $method, $route->gatherMiddleware(), $route->wheres, $controllerDigest], JSON_THROW_ON_ERROR))];
            }
        }
        $revision = (string) config('api_documentation.source_revision', '');
        $provenance = [
            'source_revision' => preg_match('/^[a-f0-9]{40}$/D', $revision) ? $revision : null,
            'runtime_fingerprint' => $runtimeDigest,
            'contract_fingerprint' => hash('sha256', json_encode([$routes, $contracts, $guides], JSON_THROW_ON_ERROR)),
            'environment' => app()->environment(),
            'scope' => 'This running source build, not an assertion that deployment equals remote main.',
        ];

        return $this->built = new ContractCatalogue($routes, $contracts, $guides, $provenance);
    }

    private function runtimeDigest(): string
    {
        $files = [];
        // Hash source only. Never inspect .env, storage, database contents, logs or vendor secrets.
        foreach (['app', 'routes', 'config', 'database/migrations'] as $directory) {
            $root = base_path($directory);
            if (! is_dir($root)) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && ! $file->isLink() && in_array($file->getExtension(), ['php', 'md', 'json'], true)) {
                    $files[substr($file->getPathname(), strlen(base_path()) + 1)] = hash_file('sha256', $file->getPathname());
                }
            }
        }
        foreach (['composer.json', 'composer.lock'] as $name) {
            if (is_file(base_path($name)) && ! is_link(base_path($name))) {
                $files[$name] = hash_file('sha256', base_path($name));
            }
        }
        ksort($files, SORT_STRING);

        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    }
}
