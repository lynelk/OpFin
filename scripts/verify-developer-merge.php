<?php

/**
 * Verify a pinned public OpFin candidate without exposing runtime credentials.
 * This helper deliberately exits 23 after success and must not deploy a runtime.
 */
$sha = $argv[1] ?? '';
$base = $argv[2] ?? '';
foreach ([$sha, $base] as $revision) {
    if (! preg_match('/^[a-f0-9]{40}$/D', $revision)) {
        fwrite(STDERR, "Exact candidate and base commit hashes are required.\n");
        exit(90);
    }
}
$root = sys_get_temp_dir().'/opfin-developer-check-'.bin2hex(random_bytes(8));
if (! mkdir($root.'/source', 0700, true) || ! mkdir($root.'/home', 0700)) {
    exit(91);
}
$environment = [
    'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
    'HOME' => $root.'/home', 'COMPOSER_HOME' => $root.'/home/.composer',
    'COMPOSER_ALLOW_SUPERUSER' => '1', 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_TIMEZONE' => 'Africa/Kampala',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'DATABASE_URL' => '',
    'DB_HOST' => '', 'DB_PORT' => '', 'DB_USERNAME' => '', 'DB_PASSWORD' => '',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array', 'MOBILE_MONEY_PROVIDER' => 'mock', 'CPAY_ENVIRONMENT' => 'sandbox',
    'CITO_ENVIRONMENT' => 'sandbox', 'BCRYPT_ROUNDS' => '4', 'CI' => 'true',
    'GIT_TERMINAL_PROMPT' => '0', 'GIT_CONFIG_NOSYSTEM' => '1',
];
$run = static function (array $command, string $directory) use ($environment): int {
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $directory, $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start an isolated verification process.');
    }

    return proc_close($process);
};
$source = $root.'/source';
foreach ([
    ['curl', '--fail', '--silent', '--show-error', '--location', '--proto', '=https', '--proto-redir', '=https',
        '--max-time', '120', 'https://codeload.github.com/lynelk/OpFin/tar.gz/'.$sha, '-o', $root.'/source.tgz'],
    ['tar', '-xzf', $root.'/source.tgz', '--strip-components=1', '--no-same-owner', '-C', $source],
    ['git', '-c', 'core.hooksPath=/dev/null', 'init', $source],
    ['git', '-C', $source, '-c', 'core.hooksPath=/dev/null', 'fetch', '--no-tags', '--depth=1', '--filter=blob:none',
        'https://github.com/lynelk/OpFin.git', $sha, $base],
    ['git', '-C', $source, '-c', 'core.hooksPath=/dev/null', 'reset', '--mixed', $sha],
] as $command) {
    $status = $run($command, $root);
    if ($status !== 0) {
        exit($status);
    }
}
$api = $source.'/apps/api';
if (! is_dir($api) || ! is_dir('/app/node_modules') || ! symlink('/app/node_modules', $api.'/node_modules')) {
    fwrite(STDERR, "The expected source or prepared build dependencies are missing.\n");
    exit(92);
}
echo 'OPFIN_DEVELOPER_VALIDATION_START '.$sha.' base='.$base.PHP_EOL;
$checks = [
    ['api-build', ['sh', 'scripts/railway-build.sh'], $api],
    ['catalogue-standalone', ['php', 'tools/opfin-mcp/test_catalogue.php'], $source],
    ['agent-bridge', ['python3', '-m', 'unittest', 'discover', '-s', 'tools/opfin-mcp', '-p', 'test_*.py', '-v'], $source],
    ['documentation-drift', ['python3', 'scripts/verify-documentation-drift.py', '--base', $base], $source],
    ['publication', ['python3', 'scripts/verify-publication-readiness.py'], $source],
    ['portal-javascript', ['node', '--check', 'public/developer-assets/portal.js'], $api],
    ['formatting', ['php', 'vendor/bin/pint', '--test',
        'app/Support/ApiDocumentation', 'app/Services/ApiDocumentation',
        'app/Providers/DeveloperDocumentationServiceProvider.php',
        'app/Http/Controllers/Api/DeveloperDocumentationController.php',
        'app/Console/Commands/ExportApiCatalogue.php', 'config/api_documentation.php',
        'routes/developer.php', 'resources/developer-guides/contracts.php',
        'resources/developer-guides/manifest.php', 'tests/Feature/DeveloperDocumentationTest.php',
        'tests/Feature/DeveloperCatalogueReviewTest.php'], $api],
];
$failed = false;
foreach ($checks as [$name, $command, $directory]) {
    echo 'OPFIN_CHECK_START '.$name.PHP_EOL;
    $status = $run($command, $directory);
    echo 'OPFIN_CHECK_RESULT '.$name.' exit='.$status.PHP_EOL;
    $failed = $failed || $status !== 0;
}
echo 'OPFIN_DEVELOPER_VALIDATION_RESULT '.$sha.' exit='.($failed ? '1' : '0').PHP_EOL;
if ($failed) {
    exit(1);
}
echo 'OPFIN_VALIDATION_ONLY_PASSED_NO_RUNTIME_DEPLOYMENT'.PHP_EOL;
exit(23);
