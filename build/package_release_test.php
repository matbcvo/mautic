<?php

echo "Preparing environment\n";
umask(022);
chdir(__DIR__);

system('rm -rf packaging');

// Preparation - Provision packaging space
mkdir(__DIR__.'/packaging');

// Grab the system git path so we can process git commands
ob_start();
passthru('which git', $systemGit);
$systemGit = trim(ob_get_clean());

// set the diff limit to ensure we get all files
system($systemGit.' config diff.renamelimit 8192');

// Checkout the version tag into the packaging space
chdir(dirname(__DIR__));
system($systemGit.' archive '.$gitSource.' | tar -x -C '.__DIR__.'/packaging', $result);

// Get a list of all files in this release
ob_start();
passthru($systemGit.' ls-tree -r -t --name-only '.$gitSource, $releaseFiles);
$releaseFiles = explode("\n", trim(ob_get_clean()));

if (0 !== $result) {
    exit;
}

chdir(__DIR__);
system('cd '.__DIR__.'/packaging && composer install --no-dev --no-scripts --optimize-autoloader && cd ..', $result);
if (0 !== $result) {
    exit;
}

// Compile prod assets
system('cd '.__DIR__.'/packaging && npm ci && npx patch-package && php bin/console mautic:assets:generate -e prod', $result);
if (0 !== $result) {
    exit;
}

echo "Environment is ready\n";


$oldVendor = __DIR__.'/mautic-minimum-version/vendor';
$newVendor = __DIR__.'/packaging/vendor';

if (!is_dir($oldVendor) || !is_dir($newVendor)) {
    echo "Error: One of the vendor directories does not exist.\n";
    exit(1);
}

$command = "comm -23 <(cd " . escapeshellarg($oldDir) . " && find . -type f | sort) " .
           "<(cd " . escapeshellarg($newDir) . " && find . -type f | sort) 2>&1";
exec($command, $output, $returnVar);

if ($returnVar !== 0) {
    echo "Error executing command: " . implode("\n", $output) . "\n";
    exit(1);
}

if (empty($output)) {
    echo "All files from the old vendor directory exist in the new vendor directory.\n";
} else {
    echo "Files present in old vendor directory but missing in new:\n";
    foreach ($output as $file) {
        echo "- " . ltrim($file, './') . "\n";
    }
}