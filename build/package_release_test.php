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
system("$systemGit remote add upstream https://github.com/mautic/mautic.git 2>/dev/null || true");
system("$systemGit fetch upstream");
$gitSource = 'upstream/6.x';
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

// ###########################################

echo "Environment is ready\n";

// Paths to vendor directories
$oldVendorPath = __DIR__.'/mautic-minimum-version/vendor';
$newVendorPath = __DIR__.'/packaging/vendor';

// Verify both vendor directories exist
if (!is_dir($oldVendorPath) || !is_dir($newVendorPath)) {
    echo "Error: Missing vendor directories\n";
    exit(1);
}

// Create temp files
$oldVendorFiles = tempnam(sys_get_temp_dir(), 'old_vendor');
$newVendorFiles = tempnam(sys_get_temp_dir(), 'new_vendor');

// Generate file lists from parent directories
$result = null;

system(sprintf(
    'cd %s && find vendor -type f -print0 | sort -z | xargs -0 -I{} echo "{}" > %s',
    escapeshellarg(dirname($oldVendorPath)),
    escapeshellarg($oldVendorFiles)
), $result);

if (0 !== $result) {
    echo "Failed to generate vendor file list\n";
    exit(1);
}

$result = null;

system(sprintf(
    'cd %s && find vendor -type f -print0 | sort -z | xargs -0 -I{} echo "{}" > %s',
    escapeshellarg(dirname($newVendorPath)),
    escapeshellarg($newVendorFiles)
), $result);

if (0 !== $result) {
    echo "Failed to generate vendor file list\n";
    exit(1);
}

// Compare the lists
$result = null;

exec(sprintf('comm -23 %s %s 2>&1',
    escapeshellarg($oldVendorFiles),
    escapeshellarg($newVendorFiles)
), $vendorDeletedFiles, $result);

// Cleanup temp files
if (file_exists($oldVendorFiles)) {
    unlink($oldVendorFiles);
}
if (file_exists($newVendorFiles)) {
    unlink($newVendorFiles);
}

// Merge results with existing deletions
if (0 === $result) {
    $deletedFiles = array_unique(array_merge(
        $deletedFiles,
        array_filter($vendorDeletedFiles, function ($path) {
            return str_starts_with($path, 'vendor/');
        })
    ));
    sort($deletedFiles);
}

mkdir(__DIR__.'/artifact');
file_put_contents(__DIR__.'/artifact/deleted_files.txt', json_encode($deletedFiles));

if (empty($vendorDeletedFiles)) {
    echo "All files from the old vendor directory exist in the new vendor directory.\n";
} else {
    echo "Files present in old vendor directory but missing in new:\n";
    foreach ($vendorDeletedFiles as $file) {
        echo "- " . $file . "\n";
    }
}