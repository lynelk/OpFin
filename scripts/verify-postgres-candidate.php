<?php

/**
 * One-shot SQLite and PostgreSQL 18 verification in an ephemeral build.
 * No inherited DB/provider credentials reach subprocesses. The synthetic PG
 * fixture uses a private Unix socket and no TCP listener. Success exits 23:
 * this script can never authorise or perform a runtime deployment.
 */
$sha = $argv[1] ?? '';
if (! preg_match('/^[a-f0-9]{40}$/D', $sha)) {
    fwrite(STDERR, "An exact candidate commit is required.\n");
    exit(90);
}
$root = '/tmp/opfin-pg-check-'.bin2hex(random_bytes(8));
$pgRoot = '/tmp/opfin-pg-fixture-'.bin2hex(random_bytes(8));
foreach ([$root.'/source', $root.'/home', $pgRoot] as $directory) {
    if (! mkdir($directory, 0700, true)) { exit(91); }
}
$environment = [
    'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
    'HOME' => $root.'/home', 'COMPOSER_HOME' => $root.'/home/.composer',
    'COMPOSER_ALLOW_SUPERUSER' => '1', 'DEBIAN_FRONTEND' => 'noninteractive',
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_TIMEZONE' => 'Africa/Kampala', 'APP_MAINTENANCE_DRIVER' => 'file',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'DATABASE_URL' => '',
    'DB_HOST' => '', 'DB_PORT' => '', 'DB_USERNAME' => '', 'DB_PASSWORD' => '',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array', 'MOBILE_MONEY_PROVIDER' => 'mock', 'CPAY_ENVIRONMENT' => 'sandbox',
    'CITO_ENVIRONMENT' => 'sandbox', 'BCRYPT_ROUNDS' => '4', 'CI' => 'true',
];
$run = static function (array $command, string $directory, array $override = []) use ($environment): int {
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes,
        $directory, array_replace($environment, $override));
    if (! is_resource($process)) { throw new RuntimeException('Cannot start isolated verification.'); }
    return proc_close($process);
};
$must = static function (array $command, string $directory, array $override = []) use ($run): void {
    $status = $run($command, $directory, $override);
    if ($status !== 0) { throw new RuntimeException('Isolated verification stage failed with exit '.$status); }
};
$started = false;
$bin = '/usr/lib/postgresql/18/bin';
$exitCode = 1;
try {
    $must(['curl', '-fLsS', '--proto', '=https', '--proto-redir', '=https', '--max-time', '120',
        'https://codeload.github.com/lynelk/OpFin/tar.gz/'.$sha, '-o', $root.'/source.tgz'], $root);
    $must(['tar', '-xzf', $root.'/source.tgz', '--strip-components=1', '--no-same-owner', '-C', $root.'/source'], $root);
    $api = $root.'/source/apps/api';
    if (! is_dir($api) || ! is_dir('/app/node_modules') || ! symlink('/app/node_modules', $api.'/node_modules')) {
        throw new RuntimeException('Expected isolated API source and prepared asset dependencies are missing.');
    }
    echo 'OPFIN_DUAL_DB_VALIDATION_START '.$sha.PHP_EOL;
    $must(['sh', 'scripts/railway-build.sh'], $api);
    echo 'OPFIN_SQLITE_BUILD_PASSED '.$sha.PHP_EOL;

    if (! in_array('pgsql', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PDO PostgreSQL is not available in this build environment.');
    }
    if (! is_file($bin.'/initdb')) {
        // Match the production database major using the project's signed
        // Debian package repository in this disposable build container only.
        $os = file_get_contents('/etc/os-release');
        if (! preg_match('/^ID="?debian"?$/m', $os)
            || ! preg_match('/^VERSION_CODENAME="?([a-z]+)"?$/m', $os, $match)
            || ! in_array($match[1], ['trixie', 'bookworm', 'bullseye'], true)) {
            throw new RuntimeException('An approved Debian fixture image or preinstalled PostgreSQL 18 is required.');
        }
        $keyDirectory = '/usr/share/postgresql-common/pgdg';
        if (! is_dir($keyDirectory) && ! mkdir($keyDirectory, 0755, true)) {
            throw new RuntimeException('Cannot prepare the signed package repository.');
        }
        $key = $keyDirectory.'/apt.postgresql.org.asc';
        $must(['curl', '-fLsS', '--proto', '=https', '--proto-redir', '=https', '--max-time', '60',
            'https://www.postgresql.org/media/keys/ACCC4CF8.asc', '-o', $key], $root);
        $repository = 'Types: deb'."\n".'URIs: https://apt.postgresql.org/pub/repos/apt'."\n"
            .'Suites: '.$match[1].'-pgdg'."\n".'Components: main'."\n".'Signed-By: '.$key."\n";
        if (file_put_contents('/etc/apt/sources.list.d/opfin-pgdg.sources', $repository) === false) {
            throw new RuntimeException('Cannot write the ephemeral signed package source.');
        }
        $must(['timeout', '120', 'apt-get', 'update', '-qq'], $root);
        $must(['timeout', '180', 'apt-get', 'install', '-y', '-qq', '--no-install-recommends', 'postgresql-18'], $root);
    }
    if (! is_file($bin.'/initdb')) { throw new RuntimeException('PostgreSQL 18 fixture binaries are unavailable.'); }
    $must([$bin.'/postgres', '--version'], $root);
    $must(['chown', '-R', 'postgres:postgres', $pgRoot], $root);
    $must(['runuser', '-u', 'postgres', '--', $bin.'/initdb', '-D', $pgRoot.'/data',
        '--no-locale', '--encoding=UTF8', '--auth=trust', '--username=opfin_test'], '/tmp');
    $must(['runuser', '-u', 'postgres', '--', $bin.'/pg_ctl', '-D', $pgRoot.'/data',
        '-l', $pgRoot.'/server.log', '-o', "-c listen_addresses='' -k ".$pgRoot.' -p 55439', '-w', 'start'], '/tmp');
    $started = true;
    $must(['runuser', '-u', 'postgres', '--', $bin.'/createdb', '-h', $pgRoot, '-p', '55439',
        '-U', 'opfin_test', 'opfin_candidate_test'], '/tmp');
    $pg = ['DB_CONNECTION' => 'pgsql', 'DB_HOST' => $pgRoot, 'DB_PORT' => '55439',
        'DB_DATABASE' => 'opfin_candidate_test', 'DB_USERNAME' => 'opfin_test', 'DB_PASSWORD' => '',
        'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SSLMODE' => 'disable'];
    $configuration = new DOMDocument;
    $configuration->load($api.'/phpunit.xml', LIBXML_NONET);
    $xpath = new DOMXPath($configuration);
    $php = $xpath->query('/phpunit/php')->item(0);
    foreach ($pg as $key => $value) {
        foreach ($xpath->query('/phpunit/php/env[@name="'.$key.'"]') as $old) { $php->removeChild($old); }
        $node = $configuration->createElement('env');
        $node->setAttribute('name', $key); $node->setAttribute('value', $value); $node->setAttribute('force', 'true');
        $php->appendChild($node);
    }
    $configuration->save($api.'/.phpunit-postgres.xml');
    echo 'OPFIN_POSTGRES18_VALIDATION_START '.$sha.PHP_EOL;
    $must(['php', 'artisan', 'config:clear'], $api, $pg);
    // A second fresh-schema pass deliberately exercises retained PostgreSQL
    // functions before PHPUnit performs its own fixture migration cycle.
    $must(['php', 'artisan', 'migrate:fresh', '--force'], $api, $pg);
    $must(['php', 'artisan', 'migrate:fresh', '--force'], $api, $pg);
    // Stop at the first failure to expose its cause instead of repeating a
    // broken fixture hundreds of times. Success still requires the full suite.
    $status = $run(['timeout', '240', 'php', 'vendor/bin/phpunit', '-c', '.phpunit-postgres.xml',
        '--stop-on-error', '--stop-on-failure'], $api, $pg);
    echo 'OPFIN_POSTGRES18_VALIDATION_RESULT '.$sha.' exit='.$status.PHP_EOL;
    if ($status !== 0) { throw new RuntimeException('The PostgreSQL 18 suite did not pass.'); }
    echo 'OPFIN_DUAL_DB_PASSED_NO_RUNTIME_DEPLOYMENT'.PHP_EOL;
    $exitCode = 23;
} catch (Throwable $error) {
    fwrite(STDERR, 'OPFIN_VALIDATION_STOP: '.$error->getMessage().PHP_EOL);
} finally {
    if ($started) {
        $run(['runuser', '-u', 'postgres', '--', $bin.'/pg_ctl', '-D', $pgRoot.'/data', '-m', 'immediate', '-w', 'stop'], '/tmp');
    }
}
exit($exitCode);
