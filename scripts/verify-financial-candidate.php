<?php

/**
 * Verify a pinned public-source candidate in an ephemeral build directory.
 * Subprocesses receive only the allow-listed synthetic test environment below.
 * No production database, credential, provider or deployment is used.
 * Exit 23 after successful verification deliberately prevents runtime rollout.
 */
$sha = $argv[1] ?? '';
if (! preg_match('/^[a-f0-9]{40}$/D', $sha)) {
    fwrite(STDERR, "An exact 40-character candidate commit is required.\n");
    exit(90);
}
$root = sys_get_temp_dir().'/opfin-financial-check-'.bin2hex(random_bytes(8));
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
];
$run = static function (array $command, string $directory) use ($environment): int {
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $directory, $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start isolated verification subprocess.');
    }

    return proc_close($process);
};
foreach ([
    ['curl', '--fail', '--silent', '--show-error', '--location', '--proto', '=https', '--proto-redir', '=https',
        '--max-time', '120', 'https://codeload.github.com/lynelk/OpFin/tar.gz/'.$sha, '-o', $root.'/source.tgz'],
    ['tar', '-xzf', $root.'/source.tgz', '--strip-components=1', '--no-same-owner', '-C', $root.'/source'],
] as $command) {
    $status = $run($command, $root);
    if ($status !== 0) { exit($status); }
}
$api = $root.'/source/apps/api';
if (! is_dir($api) || ! is_dir('/app/node_modules') || ! symlink('/app/node_modules', $api.'/node_modules')) {
    fwrite(STDERR, "Expected isolated API source and prepared build dependencies were not available.\n");
    exit(92);
}
echo 'OPFIN_FINANCIAL_VALIDATION_START '.$sha.PHP_EOL;
$status = $run(['sh', 'scripts/railway-build.sh'], $api);
echo 'OPFIN_FINANCIAL_VALIDATION_RESULT '.$sha.' exit='.$status.PHP_EOL;
if ($status !== 0) { exit($status); }
echo 'OPFIN_VALIDATION_ONLY_PASSED_NO_RUNTIME_DEPLOYMENT'.PHP_EOL;
exit(23);
