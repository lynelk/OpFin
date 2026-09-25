<?php

/**
 * One-shot build validation of a pinned public OpFin candidate.
 * Never run against a working production checkout. This creates a temporary
 * source tree and delegates tests to the existing hermetic test/build script.
 * Exit 23 after success is deliberate: this tool must not release a runtime.
 */
$sha = $argv[1] ?? 'a56289bcfbf05759d6afd844d89f7e8a39ae34db';
if (! preg_match('/^[a-f0-9]{40}$/D', $sha)) {
    fwrite(STDERR, "An exact candidate commit is required.\n");
    exit(90);
}
$root = sys_get_temp_dir().'/opfin-validate-'.bin2hex(random_bytes(8));
if (! mkdir($root, 0700, true)) {
    exit(91);
}
$archive = $root.'/source.tgz';
$commands = [
    'curl -fLsS --max-time 120 '.escapeshellarg('https://codeload.github.com/lynelk/OpFin/tar.gz/'.$sha).' -o '.escapeshellarg($archive),
    'mkdir '.escapeshellarg($root.'/source'),
    'tar -xzf '.escapeshellarg($archive).' --strip-components=1 -C '.escapeshellarg($root.'/source'),
];
foreach ($commands as $command) {
    passthru($command, $status);
    if ($status !== 0) {
        exit($status);
    }
}
$api = $root.'/source/apps/api';
if (! is_dir($api) || ! symlink('/app/node_modules', $api.'/node_modules')) {
    exit(92);
}
chdir($api);
echo 'OPFIN_VALIDATION_START '.$sha.PHP_EOL;
passthru('sh scripts/railway-build.sh', $status);
echo 'OPFIN_VALIDATION_RESULT '.$sha.' exit='.$status.PHP_EOL;
if ($status !== 0) {
    exit($status);
}
echo 'OPFIN_VALIDATION_ONLY_PASSED_NO_RUNTIME_DEPLOYMENT'.PHP_EOL;
exit(23);
